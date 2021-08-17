<?php
namespace Generator\Model;
class Generator {
	protected $dbAdapter;
	public function __construct($dbAdapter) {
		$this->dbAdapter = $dbAdapter;
	}
	protected function appPath() {
		$publicFolderPath = $_SERVER['DOCUMENT_ROOT'];
		chdir($publicFolderPath);
		return;
	}
	public function getDatabaseTables() {
		$sql = "show tables";
		$stmt = $this->dbAdapter->query($sql);
		$rows = $stmt->execute();
		$tables = [];
		foreach ($rows as $row) {
			$tables[] = reset($row);
		}
		return $tables;
	}
	public function getModules() {
		$modules = array_diff(scandir('module'), ['..', '.']);
		$key = array_search('Generator', $modules);
		unset($modules[$key]);
		return $modules;
	}
	public function generateForm($module, $table) {
		$directories = array_diff(scandir("module/$module/src"), ['..', '.']);
		if (!in_array('Form', $directories)) {
			mkdir("module/$module/src/Form", 0777);
		}
		chdir("module/$module/src/Form");
		$formDirPath = getcwd();
		$filename = $this->getFormName($table);
		$file = $formDirPath . '/' . $filename . '.php';
		$this->writeCode($file, $table, $module);
	}
	protected function getFormName($tableName) {
		$name = '';
		$formName = preg_replace("/[^A-Za-z0-9 ]/", '_', $tableName);
		$stringParts = explode("_", $formName);
		foreach ($stringParts as $part) {
			$partArray = str_split($part);
			$partArray[0] = strtoupper($partArray[0]);
			$name .= implode('', $partArray);
		}
		return $name;
	}
	protected function getFieldLabel($field) {
		$label = '';
		$formName = preg_replace("/[^A-Za-z0-9 ]/", '_', $field);
		$stringParts = explode("_", $formName);
		foreach ($stringParts as $part) {
			$partArray = str_split($part);
			$partArray[0] = strtoupper($partArray[0]);
			$partArray[] = ' ';
			$label .= implode('', $partArray);
		}
		return $label;
	}
	protected function writeCode($file, $table, $module) {
		$sql = "SHOW COLUMNS FROM $table";
		$stmt = $this->dbAdapter->query($sql);
		$rows = $stmt->execute();
		$fields = array();
		foreach ($rows as $row) {
			$fields[] = $row;
		}
		$code = "<?php\n";
		$code .= "namespace $module\Form;\n\n";
		$code .= "use Laminas\Form\Form;\n";
		$code .= "use Laminas\InputFilter\Factory as InputFactory;\n";
		$code .= "use Laminas\InputFilter\InputFilter;\n";
		$code .= "use Laminas\InputFilter\InputFilterAwareInterface;\n";
		$code .= "use Laminas\InputFilter\InputFilterInterface;\n\n";
		$code .= "class {$this->getFormName($table)} extends Form implements InputFilterAwareInterface {\n";
		$code .= "protected \$inputFilter;\n";
		$code .= "public function __construct(\$name = 'null') {\n";
		$code .= "parent::__construct('$table');\n";
		$code .= "\$this->setAttribute('method', 'post');\n";
		$code .= "\$this->setAttribute('id', '$table');\n";
		foreach ($fields as $field) {
			$code .= "\$this->add([\n";
			$code .= "'name' => '{$field['Field']}',\n";
			$code .= "'required' => 'required',\n";
			$code .= "'attributes' => [\n";
			$code .= "'type' => 'text',\n";
			$code .= "'id' => '{$field['Field']}',\n";
			$code .= "],\n";
			$code .= "'options' => [\n";
			$code .= "'label' => '{$this->getFieldLabel($field['Field'])}',\n";
			$code .= "],\n";
			$code .= "]);\n";
		}
		$code .= "}\n\n";
		$code .= "public function getInputFilter() {\n";
		$code .= "if (!\$this->inputFilter) {\n";
		$code .= "\$inputFilter = new InputFilter();\n";
		$code .= "\$factory = new InputFactory();\n";
		foreach ($fields as $field) {
			$code .= "\$inputFilter->add(\$factory->createInput([\n";
			$code .= "'name' => '{$field['Field']}',\n";
			$required = ($field['Null'] == 'NO') ? 'true' : 'false';
			$code .= "'required' => $required,\n";
			$code .= "'filters' => [\n";
			$code .= "['name' => 'StripTags'],\n";
			$code .= "['name' => 'StringTrim'],\n";
			$code .= "],\n";
			$code .= "'validators' => [\n";
			if ($field['Null'] == 'NO') {
			$code .= "[\n";
			$code .= "'name' => 'NotEmpty',\n";
			$code .= "'options' => ['message' => '{$this->getFieldLabel($field['Field'])} cannot be empty'],\n";
			$code .= "],\n";
			}
			$code .= "],\n";
			$code .= "]));\n";
		}
		$code .= "\$this->inputFilter = \$inputFilter;\n";
		$code .= "}\n";
		$code .= "return \$this->inputFilter;\n";
		$code .= "}\n}";
		file_put_contents($file, $code);
	}
	public function generateModule($moduleName, $createController, $controllerName = null) {
		$this->createModuleDirectoryStructure($moduleName);
		$this->createModuleFile($moduleName);
		$this->createModuleConfigFile($moduleName);
		if ($createController && !empty($controllerName)) {
			$this->createControllerForModule($moduleName, $controllerName, $actionName);
		}
		$this->addModuleInProject($moduleName);
	}
	protected function createModuleDirectoryStructure($moduleName) {
		//module
		chdir('module');
		mkdir($moduleName, 0777);
		//src
		chdir($moduleName);
		mkdir('config', 0777);
		mkdir('src', 0777);
		mkdir('view', 0777);
		//view
		chdir('view');
		mkdir(strtolower($moduleName), 0777);
		mkdir('error', 0777);
		mkdir('layout', 0777);
		//controller
		chdir('..');
		chdir('src');
		mkdir('Controller', 0777);
		return;
	}
	protected function createModuleFile($moduleName) {
		$moduleFileTemplate = $this->getModuleFileTemplate();
		$moduleFileTemplate = str_replace('ModuleName', $moduleName, $moduleFileTemplate);
		$this->appPath();
		chdir('module');
		chdir($moduleName);
		chdir('src');
		$handle = fopen('Module.php', 'w+');
		fwrite($handle, $moduleFileTemplate);
	}
	protected function getModuleFileTemplate() {
		$this->appPath();
		chdir('module/Generator/src/Model');
		return file_get_contents('Module.php');
	}
	protected function createModuleConfigFile($moduleName) {
		$array = $this->getModuleConfigFileTemplate();
		$this->appPath();
		chdir("module/$moduleName/config");
		$handle = fopen('module.config.php', 'w+');
		$content = "<?php\nreturn [\n";
		$content .= $this->writeConfig($array);
		$content .= "];";
		fwrite($handle, $content);
	}
	protected function getModuleConfigFileTemplate() {
		$this->appPath();
		chdir('module/Generator/src/Model');
		$array = include('module.config.php');
		return $array;
	}
	protected function writeConfig($array, $content = null, $count = null) {
		$content = '';
		$count = $count == null ? 1 : $count;
		foreach ($array as $key => $config) {
			if(stripos($key, '\Controller\\')) {
				$key = explode("\\", $key);
				$key = "Controller\\".end($key)."::class";
			}
			$intends = $this->getIntends($count);
			if($key != 'template_path_stack' || is_int($key)) {
				if(!is_array($config)) {
					if(stripos($config, '\Controller\\')) {
						$config = explode("\\", $config);
						$config = "Controller\\".end($config)."::class";
					}
					if(strpos($config,"::") === false) $config = "'$config'";
					if(strpos($key,"::") === false) $key = "'$key'";
					$content .= "{$intends}$key => $config,\n";
				} else {
					$content .= "$intends'$key' => [\n";
					$content .= $this->writeConfig($config, $content, $count + 1);
					$content .= "$intends],\n";
				}
			} else {
				$content .= "$intends'$key' => [__NAMESPACE__ => __DIR__ . '/../view'],\n";
			}
		}
		
		$search = ["'Laminas\Router\Http\Segment'","'Laminas\Router\Http\Literal'","'Laminas\ServiceManager\Factory\InvokableFactory'"];
		$replace = ['Segment::class','Literal::class','InvokableFactory::class'];
		$content = str_replace($search, $replace, $content);
		return $content;
	}
	protected function getIntends($count) {
		$spaces = $count * 4;
		$intends = '';
		for ($i = 1; $i <= $spaces; $i++) {
			$intends .= ' ';
		}
		return $intends;
	}
	/**
	 * Create controller
	 */
	public function createControllerForModule($moduleName, $controllerName, $actionName) {
		$this->appPath();
		chdir("module/$moduleName/src/Controller");
		$controllerFullName = ucfirst($controllerName) . 'Controller';
		$file = ucfirst($controllerFullName).".php";
		$content = "<?php\n";
		$content .= "namespace $moduleName\Controller;\n\n";
		$content .= "use Laminas\Mvc\Controller\AbstractActionController;\n";
		$content .= "use Laminas\View\Model\ViewModel;\n\n";
		$content .= "class $controllerFullName extends AbstractActionController\n{\n";
		if(empty($actionName)) {
			$content .= "\tpublic function indexAction()\n\t{\n";
			$content .= "\t\treturn new ViewModel();\n";
			$content .= "\t}\n";
		} else {
			$acts = explode(",",$actionName);
			foreach($acts as $act) {
				$content .= "\tpublic function ".strtolower($act)."Action()\n\t{\n";
				$content .= "\t\treturn new ViewModel();\n";
				$content .= "\t}\n";
			}
		}
		$content .= "}";
		file_put_contents($file, $content);
		$this->addRouterForController($controllerName, $moduleName);
		$this->addViewFolderForController($controllerName, $moduleName, $actionName);
	}
	protected function addRouterForController($controllerName, $moduleName) {
		$moduleConfig = $this->getModuleConfig($moduleName);
		$controllerName = ucfirst($controllerName);
		$controllerFullName = $controllerName . 'Controller';
		$moduleConfig['controllers']['factories']["Controller\\$controllerFullName::class"] = 'InvokableFactory::class';
		$controllerRouter = [
			'type' => 'Segment::class',
			'options' => [
			'route' => '/'.strtolower($controllerName) . '[/:action[/:id]]',
			'constraints' => [
			'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
			'id' => '[0-9]+',
		],
		'defaults' => [
			'controller' => "Controller\\$controllerFullName::class",
			'action' => 'index',
		],
		],
		];
		$moduleConfig['router']['routes'][strtolower($controllerName)] = $controllerRouter;
		$content = "<?php\nnamespace $moduleName;\n";
		$content .= "use Laminas\\Router\\Http\Segment;\nuse Laminas\\ServiceManager\\Factory\\InvokableFactory;\n";
		$content .= "return [\n" . $this->writeConfig($moduleConfig) ."];";
		file_put_contents('module.config.php', $content);
		return;
	}
	protected function getModuleConfig($moduleName) {
		$this->appPath();
		chdir("module/$moduleName/config");
		return include 'module.config.php';
	}
	protected function addViewFolderForController($controllerName, $moduleName, $actionName) {
		$this->appPath();
		chdir("module/".strtolower($moduleName)."/view/".strtolower($moduleName));
		mkdir(strtolower($controllerName), 0777);
		chdir(strtolower($controllerName));
		if($actionName !="") {
			$acts = explode(",",$actionName);
			foreach($acts as $act) {
				$hndl = fopen(strtolower($act).'.phtml', 'w+');
				fwrite($hndl, $controllerName.'::'.$act);
			}
		} else {
			$hndl = fopen('index.phtml', 'w+');
			fwrite($hndl, $controllerName.'::index');
		}
	}
	protected function addModuleInProject($moduleName) {
		$applicationConfig = $this->getApplicationConfig();
		if (!in_array($moduleName, $applicationConfig)) {
			$applicationConfig[] = $moduleName;
			$hand = fopen('modules.config.php', 'w+');
			$ctxt = "<?php\nreturn [\n";
			$ctxt .= "\t'".implode("',\n\t'",$applicationConfig)."'\n";
			$ctxt .= "];";
			fwrite($hand, $ctxt);
		}
	}
	protected function getApplicationConfig() {
		$this->appPath();
		chdir('config');
		return include 'modules.config.php';
	}
}