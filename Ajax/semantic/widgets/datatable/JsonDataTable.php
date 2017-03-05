<?php

namespace Ajax\semantic\widgets\datatable;

use Ajax\semantic\widgets\datatable\DataTable;
use Ajax\service\JReflection;
use Ajax\common\html\BaseHtml;
use Ajax\service\AjaxCall;
use Ajax\JsUtils;
use Ajax\semantic\html\collections\menus\HtmlMenu;

class JsonDataTable extends DataTable {
	protected $_modelClass="_jsonArrayModel";

	public function __construct($identifier, $model, $modelInstance=NULL) {
		parent::__construct($identifier, $model, $modelInstance);
	}

	protected function _generateContent($table){
		$this->_addRowModel($table);
		$this->_rowClass="_json";
		parent::_generateContent($table);
	}

	protected function _addRowModel($table){
		$object=JReflection::jsonObject($this->_model);
		$row=$this->_generateRow($object, $table,"_jsonArrayChecked");
		$row->setClass($this->_modelClass);
		$row->addToProperty("style","display:none;");
		$table->getBody()->_addRow($row);
	}

	/**
	 * {@inheritDoc}
	 * @see DataTable::_associatePaginationBehavior()
	 */
	protected function _associatePaginationBehavior(HtmlMenu $menu,JsUtils $js=NULL){
		$callback=null;
		if(isset($js)){
			$id=$this->identifier;
			$offset=$js->scriptCount();
			$this->run($js);
			$callback=$js->getScript($offset);
			$callback.=$js->trigger("#".$id." [name='selection[]']","change",false)."$('#".$id." tbody .ui.checkbox').checkbox();".$js->execOn("change", "#".$id." [name='selection[]']", $this->_getCheckedChange($js));
			$callback.=";var page=parseInt($(self).attr('data-page'));
			$('#pagination-{$id} .item').removeClass('active');
			$('#pagination-{$id} [data-page='+page+']:not(.no-active)').addClass('active');
			$('#pagination-{$id} ._firstPage').attr('data-page',Math.max(1,page-1));
			var lastPage=$('#pagination-{$id} ._lastPage');lastPage.attr('data-page',Math.min(lastPage.attr('data-max'),page+1));";
		}
		if(isset($this->_urls["refresh"]))
			$this->jsonArrayOnClick($menu, $this->_urls["refresh"],"post","{'p':$(this).attr('data-page')}",$callback);
	}

	/**
	 * Returns a new AjaxCall object, must be compiled using $jquery object
	 * @param string $url
	 * @param string $method
	 * @param string $params
	 * @param callable $jsCallback
	 * @return AjaxCall
	 */
	public function jsJsonArray($url, $method="get", $params="{}", $jsCallback=NULL,$parameters=[]){
		$parameters=\array_merge($parameters,["modelSelector"=>"#".$this->_identifier." tr.".$this->_modelClass,"url"=>$url,"method"=>$method,"params"=>$params,"jsCallback"=>$jsCallback]);
		return new AjaxCall("jsonArray", $parameters);
	}

	public function jsonArrayOn(BaseHtml $element,$event,$url, $method="get", $params="{}", $jsCallback=NULL,$parameters=[]){
		return $element->_addEvent($event, $this->jsJsonArray($url,$method,$params,$jsCallback,$parameters));
	}

	public function jsonArrayOnClick(BaseHtml $element,$url, $method="get", $params="{}", $jsCallback=NULL,$parameters=[]){
		return $this->jsonArrayOn($element, "click", $url,$method,$params,$jsCallback,$parameters);
	}

	/**
	 * Paginates the DataTable element with a Semantic HtmlPaginationMenu component
	 * @param number $page the active page number
	 * @param number $total_rowcount the total number of items
	 * @param number $items_per_page The number of items per page
	 * @param number $pages_visibles The number of visible pages in the Pagination component
	 * @return DataTable
	 */
	public function paginate($page,$total_rowcount,$items_per_page=10,$pages_visibles=null){
		return parent::paginate($page, $total_rowcount,$items_per_page,null);
	}
}