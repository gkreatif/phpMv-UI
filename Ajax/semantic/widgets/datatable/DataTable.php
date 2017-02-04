<?php

namespace Ajax\semantic\widgets\datatable;

use Ajax\common\Widget;
use Ajax\JsUtils;
use Ajax\semantic\html\collections\table\HtmlTable;
use Ajax\semantic\html\elements\HtmlInput;
use Ajax\semantic\html\collections\menus\HtmlPaginationMenu;
use Ajax\semantic\html\modules\checkbox\HtmlCheckbox;
use Ajax\semantic\html\elements\HtmlButton;
use Ajax\semantic\html\base\constants\Direction;
use Ajax\service\JArray;
use Ajax\semantic\html\base\HtmlSemDoubleElement;
use Ajax\semantic\widgets\base\InstanceViewer;
use Ajax\semantic\html\collections\table\traits\TableTrait;

/**
 * DataTable widget for displaying list of objects
 * @version 1.0
 * @author jc
 * @since 2.2
 *
 */
class DataTable extends Widget {
	use TableTrait;
	protected $_searchField;
	protected $_urls;
	protected $_pagination;
	protected $_hasCheckboxes;
	protected $_compileParts;

	public function run(JsUtils $js){
		if($this->_hasCheckboxes && isset($js)){
			$js->execOn("change", "#".$this->identifier." [name='selection[]']", "
		var \$parentCheckbox=\$('#ck-main-ck-{$this->identifier}'),\$checkbox=\$('#{$this->identifier} [name=\"selection[]\"]'),allChecked=true,allUnchecked=true;
		\$checkbox.each(function() {if($(this).prop('checked')){allUnchecked = false;}else{allChecked = false;}});
		if(allChecked) {\$parentCheckbox.checkbox('set checked');}else if(allUnchecked){\$parentCheckbox.checkbox('set unchecked');}else{\$parentCheckbox.checkbox('set indeterminate');}");
		}
		parent::run($js);
	}

	public function __construct($identifier,$model,$modelInstance=NULL) {
		parent::__construct($identifier, $model,$modelInstance);
		$this->_init(new InstanceViewer($identifier), "table", new HtmlTable($identifier, 0,0), false);
	}

	/**
	 * {@inheritDoc}
	 * @see \Ajax\semantic\html\collections\table\TableTrait::getTable()
	 */
	protected function getTable() {
		return $this->content["table"];
	}


	public function compile(JsUtils $js=NULL,&$view=NULL){
		$this->_instanceViewer->setInstance($this->_model);
		$captions=$this->_instanceViewer->getCaptions();

		$table=$this->content["table"];

		if($this->_hasCheckboxes){
			$this->_generateMainCheckbox($captions);
		}

		$table->setRowCount(0, \sizeof($captions));
		$table->setHeaderValues($captions);
		if(isset($this->_compileParts))
			$table->setCompileParts($this->_compileParts);
		if(isset($this->_searchField) && isset($js)){
			$this->_searchField->postOn("change", $this->_urls,"{'s':$(this).val()}","#".$this->identifier." tbody",["preventDefault"=>false,"jqueryDone"=>"replaceWith"]);
		}

		$this->_generateContent($table);

		if($this->_hasCheckboxes && $table->hasPart("thead")){
				$table->getHeader()->getCell(0, 0)->addToProperty("class","no-sort");
		}

		if(isset($this->_pagination) && $this->_pagination->getVisible()){
			$this->_generatePagination($table);
		}
		if(isset($this->_toolbar)){
			$this->_setToolbarPosition($table, $captions);
		}
		$this->content=JArray::sortAssociative($this->content, [PositionInTable::BEFORETABLE,"table",PositionInTable::AFTERTABLE]);
		return parent::compile($js,$view);
	}

	private function _generateMainCheckbox(&$captions){
		$ck=new HtmlCheckbox("main-ck-".$this->identifier,"");
		$ck->setOnChecked("$('#".$this->identifier." [name=%quote%selection[]%quote%]').prop('checked',true);");
		$ck->setOnUnchecked("$('#".$this->identifier." [name=%quote%selection[]%quote%]').prop('checked',false);");
		\array_unshift($captions, $ck);
	}

	protected function _generateContent($table){
		$objects=$this->_modelInstance;
		if(isset($this->_pagination)){
			$objects=$this->_pagination->getObjects($this->_modelInstance);
		}
		InstanceViewer::setIndex(0);
		$table->fromDatabaseObjects($objects, function($instance){
			$this->_instanceViewer->setInstance($instance);
			InstanceViewer::$index++;
			$result= $this->_instanceViewer->getValues();
			if($this->_hasCheckboxes){
				$ck=new HtmlCheckbox("ck-".$this->identifier,"");
				$field=$ck->getField();
				$field->setProperty("value",$this->_instanceViewer->getIdentifier());
				$field->setProperty("name", "selection[]");
				\array_unshift($result, $ck);
			}
			return $result;
		});
	}

	private function _generatePagination($table){
		$footer=$table->getFooter();
		$footer->mergeCol();
		$menu=new HtmlPaginationMenu("pagination-".$this->identifier,$this->_pagination->getPagesNumbers());
		$menu->floatRight();
		$menu->setActiveItem($this->_pagination->getPage()-1);
		$footer->setValues($menu);
		$menu->postOnClick($this->_urls,"{'p':$(this).attr('data-page')}","#".$this->identifier." tbody",["preventDefault"=>false,"jqueryDone"=>"replaceWith"]);
	}

	protected function _setToolbarPosition($table,$captions=NULL){
		switch ($this->_toolbarPosition){
			case PositionInTable::BEFORETABLE:
			case PositionInTable::AFTERTABLE:
				if(isset($this->_compileParts)===false){
					$this->content[$this->_toolbarPosition]=$this->_toolbar;
				}
				break;
			case PositionInTable::HEADER:
			case PositionInTable::FOOTER:
			case PositionInTable::BODY:
				$this->addToolbarRow($this->_toolbarPosition,$table, $captions);
				break;
		}
	}

	/**
	 * Associates a $callback function after the compilation of the field at $index position
	 * The $callback function can take the following arguments : $field=>the compiled field, $instance : the active instance of the object, $index: the field position
	 * @param int $index postion of the compiled field
	 * @param callable $callback function called after the field compilation
	 * @return \Ajax\semantic\widgets\datatable\DataTable
	 */
	public function afterCompile($index,$callback){
		$this->_instanceViewer->afterCompile($index,$callback);
		return $this;
	}

	private function addToolbarRow($part,$table,$captions){
		$hasPart=$table->hasPart($part);
		if($hasPart){
			$row=$table->getPart($part)->addRow(\sizeof($captions));
		}else{
			$row=$table->getPart($part)->getRow(0);
		}
		$row->mergeCol();
		$row->setValues([$this->_toolbar]);
	}

	public function getHtmlComponent(){
		return $this->content["table"];
	}

	public function getUrls() {
		return $this->_urls;
	}

	public function setUrls($urls) {
		$this->_urls=$urls;
		return $this;
	}

	public function paginate($items_per_page=10,$page=1){
		$this->_pagination=new Pagination($items_per_page,4,$page);
	}

	public function getHasCheckboxes() {
		return $this->_hasCheckboxes;
	}

	public function setHasCheckboxes($_hasCheckboxes) {
		$this->_hasCheckboxes=$_hasCheckboxes;
		return $this;
	}

	public function refresh($compileParts=["tbody"]){
		$this->_compileParts=$compileParts;
		return $this;
	}
	/**
	 * @param string $caption
	 * @param callable $callback
	 * @return callable
	 */
	private function getFieldButtonCallable($caption,$callback=null){
		return $this->getCallable("getFieldButton",[$caption],$callback);
	}

	/**
	 * @param callable $thisCallback
	 * @param array $parameters
	 * @param callable $callback
	 * @return callable
	 */
	private function getCallable($thisCallback,$parameters,$callback=null){
		$result=function($instance) use($thisCallback,$parameters,$callback){
			$object=call_user_func_array(array($this,$thisCallback), $parameters);
			if(isset($callback)){
				if(\is_callable($callback)){
					$callback($object,$instance);
				}
			}
			if($object instanceof HtmlSemDoubleElement){
				$object->setProperty("data-ajax",$this->_instanceViewer->getIdentifier());
			}
			return $object;
		};
		return $result;
	}

	/**
	 * @param string $caption
	 * @return HtmlButton
	 */
	private function getFieldButton($caption){
		return new HtmlButton("",$caption);
	}

	/**
	 * Inserts a new Button for each row
	 * @param string $caption
	 * @param callable $callback
	 * @return \Ajax\semantic\widgets\datatable\DataTable
	 */
	public function addFieldButton($caption,$callback=null){
		$this->addField($this->getCallable("getFieldButton",[$caption],$callback));
		return $this;
	}

	/**
	 * Inserts a new Button for each row at col $index
	 * @param int $index
	 * @param string $caption
	 * @param callable $callback
	 * @return \Ajax\semantic\widgets\datatable\DataTable
	 */
	public function insertFieldButton($index,$caption,$callback=null){
		$this->insertField($index, $this->getFieldButtonCallable($caption,$callback));
		return $this;
	}

	/**
	 * Inserts a new Button for each row in col at $index
	 * @param int $index
	 * @param string $caption
	 * @param callable $callback
	 * @return \Ajax\semantic\widgets\datatable\DataTable
	 */
	public function insertInFieldButton($index,$caption,$callback=null){
		$this->insertInField($index, $this->getFieldButtonCallable($caption,$callback));
		return $this;
	}

	private function addDefaultButton($icon,$class=null,$callback=null){
		$this->addField($this->getCallable("getDefaultButton",[$icon,$class],$callback));
		return $this;
	}

	private function insertDefaultButtonIn($index,$icon,$class=null,$callback=null){
		$this->insertInField($index,$this->getCallable("getDefaultButton",[$icon,$class],$callback));
		return $this;
	}

	private function getDefaultButton($icon,$class=null){
		$bt=$this->getFieldButton("");
		$bt->asIcon($icon);
		if(isset($class))
			$bt->addToProperty("class", $class);
		return $bt;
	}

	public function addDeleteButton($callback=null){
		return $this->addDefaultButton("remove","delete red basic",$callback);
	}

	public function addEditButton($callback=null){
		return $this->addDefaultButton("edit","edit basic",$callback);
	}

	public function addEditDeleteButtons($callbackEdit=null,$callbackDelete=null){
		$this->addEditButton($callbackEdit);
		$index=$this->_instanceViewer->visiblePropertiesCount()-1;
		$this->insertDeleteButtonIn($index,$callbackDelete);
		return $this;
	}

	public function insertDeleteButtonIn($index,$callback=null){
		return $this->insertDefaultButtonIn($index,"remove","delete red basic",$callback);
	}

	public function insertEditButtonIn($index,$callback=null){
		return $this->insertDefaultButtonIn($index,"edit","edit basic",$callback);
	}

	public function addSearchInToolbar($position=Direction::RIGHT){
		return $this->addInToolbar($this->getSearchField())->setPosition($position);
	}

	public function getSearchField(){
		if(isset($this->_searchField)===false){
			$this->_searchField=new HtmlInput("search-".$this->identifier,"search","","Search...");
			$this->_searchField->addIcon("search",Direction::RIGHT);
		}
		return $this->_searchField;
	}

	/**
	 * The callback function called after the insertion of each row when fromDatabaseObjects is called
	 * callback function takes the parameters $row : the row inserted and $object: the instance of model used
	 * @param callable $callback
	 * @return DataTable
	 */
	public function onNewRow($callback) {
		$this->content["table"]->onNewRow($callback);
		return $this;
	}
}