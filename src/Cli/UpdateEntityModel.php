<?php namespace Phlex\Cli;

use App\Env;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateEntityModel extends Command{
	protected function configure() {
		$this
			->setName('entity:update')
			->setDescription('Updates model from database table')
			->addArgument('name', InputArgument::REQUIRED);
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		$name = $input->getArgument('name');
		$class = "\\App\\Entity\\".$name."\\".$name;

		/** @var \Phlex\RedFox\Repository $repository */
		$repository = $class::repository();
		/** @var \Phlex\RedFox\Model $model */
		$model = $class::model();
		/**  */
		$table =  $repository->getDataSource()->getTable();
		$access = $repository->getDataSource()->getAccess();
		$fields = $access->getFieldList($table);

		$missings = array_diff($fields, $model->getFields());
		$unwanteds = array_diff($model->getFields(), $fields);

		//$dir = Env::instance()->path_root.'App/Entity/'.$name;

		$ref = new \ReflectionClass($class.'Model');
		$fieldsMethod = $ref->getMethod('fields');

		$start = $fieldsMethod->getStartLine();
		$end = $fieldsMethod->getEndLine()-1;
		$source = file($ref->getFileName());
		$body = array_slice($source, $start, $end-$start);

		// Handle unwanted fields
		foreach ($body as $i => $line) {
			$body[$i] = trim($line);
		}

		if(count($unwanteds)) foreach ($body as $i => $line){
			foreach ($unwanteds as $unwanted){
				if(strpos($line, "\$this->addField('".$unwanted."'") === 0){
					$body[$i] = '//Deleted: '.$line;
				}
			}
		}

		if(count($missings)) {
			$fieldinfo = [];
			$rawfieldinfo = $access->getFieldData($table);
			foreach ($rawfieldinfo as $rawfield){
				$fieldinfo[$rawfield['Field']] = $rawfield;
			}
			foreach ($missings as $missing) {
				$field = $fieldinfo[$missing];
				$newline = "\$this->addField('".$missing."', (new ".$this->fieldSelector($field['Type'], $missing)."(\"".$field['Type']."\"))";
				$options = $access->getEnumValues($table, $missing);
				if(count($options)){
					$newline.="->setOptions(['".join("','", $options)."'])";
				}
				if($field['Null'] == 'NO'){
					$newline.="->notNullable()";
				}
				if($missing == 'id'){
					$newline.="->constant()";
				}
				$newline.=");";
				$body[] = $newline;
			}
		}

		foreach ($body as $i => $line) {
			$body[$i] = "\t\t".$line."\n";
		}

		array_splice($source, $start, $end-$start,$body);

		file_put_contents($ref->getFileName(), join('', $source));

	}

	protected function fieldSelector($dbtype, $fieldName){
		if($dbtype == 'tinyint(1)') return '\Phlex\RedFox\Fields\BoolField';
		if($dbtype == 'date') return '\Phlex\RedFox\Fields\DateField';
		if($dbtype == 'datetime') return '\Phlex\RedFox\Fields\DateTimeField';
		if($dbtype == 'float') return '\Phlex\RedFox\Fields\FloatField';

		if(strpos($dbtype, 'int(11) unsigned')===0 && (substr($fieldName, -2) == 'Id' || $fieldName == 'id')) return '\Phlex\RedFox\Fields\IdField';
		if(strpos($dbtype, 'int')===0) return '\Phlex\RedFox\Fields\IntegerField';
		if(strpos($dbtype, 'tinyint')===0) return '\Phlex\RedFox\Fields\IntegerField';
		if(strpos($dbtype, 'smallint')===0) return '\Phlex\RedFox\Fields\IntegerField';
		if(strpos($dbtype, 'mediumint')===0) return '\Phlex\RedFox\Fields\IntegerField';
		if(strpos($dbtype, 'bigint')===0) return '\Phlex\RedFox\Fields\IntegerField';

		if(strpos($dbtype, 'varchar')===0) return '\Phlex\RedFox\Fields\StringField';
		if(strpos($dbtype, 'char')===0) return '\Phlex\RedFox\Fields\StringField';
		if(strpos($dbtype, 'text')===0) return '\Phlex\RedFox\Fields\StringField';
		if(strpos($dbtype, 'text')===0) return '\Phlex\RedFox\Fields\StringField';
		if(strpos($dbtype, 'tinytext')===0) return '\Phlex\RedFox\Fields\StringField';
		if(strpos($dbtype, 'mediumtext')===0) return '\Phlex\RedFox\Fields\StringField';
		if(strpos($dbtype, 'longtext')===0) return '\Phlex\RedFox\Fields\StringField';

		if(strpos($dbtype, 'set')===0) return '\Phlex\RedFox\Fields\SetField';
		if(strpos($dbtype, 'enum')===0) return '\Phlex\RedFox\Fields\EnumField';

		return '\Phlex\RedFox\Fields\UnsupportedField';
	}

}