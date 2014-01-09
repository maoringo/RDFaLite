<?php
/** @prefix : <http://purl.org/net/ns/doas#> .
<> a :PHPScript;
 :title "Microdata Parser";
 :created "2012-10-01";
 :release [:revision "0.70"; :created "2012-10-06"];
 :description """Extracts PHP/JSON structure from HTML with microdata annotation.
 Based on MicrodataPHP.php by Lin Clark. License is the same as that of the original: MIT license.
 Simple usage is the same as the orignal:
	$md   = new RDFaLite("http://example.org");
	$json = $md->json();
 - You can provide HTML data as the second param of RDFaLite(). Also, can provide base URI as the third param.
 - $md->json(true) will return structured property values for JSON representation.
 - $md->jsonld will return JSON-LD style RDFa Lite.
 Note text property value is extracted by DOM textContent, which might cause XSS problem. Make sure to escape when print property values in HTML.
 """ ;
 :license <http://www.opensource.org/licenses/mit-license.php> .
 */
/**
 * Original: MicrodataPHP
 * http://github.com/linclark/MicrodataPHP
 * Copyright (c) 2011 Lin Clark
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 *
 * Based on MicrodataJS
 * http://gitorious.org/microdatajs/microdatajs
 * Copyright (c) 2009-2011 Philip Jägenstedt
 */
/**
 * Extracts RDFa Lite from HTML.
 * original from MicrodataPHP, modified by masaka
 * modified for RDFa Lite by Maori Ito 
 *
 * Currently supported formats:
 *   - PHP object
 *   - JSON
 *   - JSON-LD
 */
class RDFaLite {
	public $dom;
	
	//@@ methods from original MicrodataPhp (with some modification) ////////////////////////////
	/**
	 * constructor. prepare DOMDocument object, register methods, load HTML and get DOM
	 * @param string $url	URL of source HTML, or base URL if $data is provided
	 * @param string $data	HTML data (optional)
	 * @param string $base	base URI to resolove relative URIs
	 */
	public function __construct($url, $data='', $base=''){
		$dom = new RDFaLitePhpDOMDocument();
		$dom->registerNodeClass('DOMDocument', 'RDFaLitePhpDOMDocument');
		$dom->registerNodeClass('DOMElement', 'RDFaLitePhpDOMElement');
		$dom->preserveWhiteSpace = false;
		@$dom->loadHTML($this->check_meta($url, $data));
		//@$dom->loadHTMLFile($url); // to allow direct data input e.g. form field, also to get around of DOMDocument's character encoding problem
		$this->dom = $dom;
		$this->baseUri = $base ? $base : $url;
	}
	
	/**
	 * get array of PHP objects that have parsed microdata item information
	 * @param boolean $use_structure	if true, return structured property value (array of value, value tyep e.g. uri / literal, and lang)
	 * @return array	array of PHP objects
	 */
	public function obj($use_structure=false) {
		$result = array();
		foreach ($this->dom->getItems() as $item) {
			array_push($result, $this->getNames($item, array()));
		}
		if(! $use_structure) $this->to_simple_value($result);
		return $result;
	}
	
	/**
	* Retrieve RDFa Lite in JSON format.
	*
	* @param boolean $use_structure	if true, return property value as object
	* @return
	*   See obj().
	*
	* @todo RDFaLiteJS allows callers to pass in a function to format the JSON.
	* Consider adding such functionality.
	*/
	public function json($use_structure=false) {
		return json_encode($this->obj($use_structure));
	}
	
	/**
	 * originally getObject().
	 * This is the step defined in RDFa Lite spec §2.5 Associating names with items
	 * @param object $item	root element of an item
	 * @return object	id, type and properties of the item
	 */
     //ここで渡すためのオブジェクトに入れているらしい。
	protected function getNames($item) {
		/* numbered comments are steps described in RDFa Lite spec §2.5 */
		//1. Let results, memory, and pending be empty lists of elements
		// @original microdataPHP has $memory as second param, passed from parent, but its better to set memory within this function (as defined in RDFaLite spec)
//        print "item\n";
//        print_r($item);
		$result = new stdClass();
		$result->properties = array();
		$memory = array();
		//2. Add the element root (=$item) to memory.
		$memory[] = $this->test_id($item);
		$item->baseUri = $this->baseUri; //set base for resolveUri()
	
		// Add typeof.
        //print_r($item);
		if ($typeof = $item->typeof()) {
			$result->type = $typeof;
		}
		// Add prefix. 
		if ($prefix = $item->prefix()) {
            //vocabと同じ場所にあるprefixはここで渡す。
			$result->prefix = $prefix;
		}
		// Add itemid. 
		if ($itemid = $item->itemId($this->baseUri)) {
			$result->id = $itemid;
		}
        
    //separate prefix and property 
        $propprefix = $item->getPending($this->baseUri);

		// Add properties.
		//steps 3, 4, 9 (add child elements to pending) are processed in getPending()

		foreach ($propprefix[0] as $elem) {
			//5. Loop: If pending is empty, jump to the step labeled end of loop.
			//6. Remove an element from pending and let current be that element.
			//i.e. $elem is 'current', returned array from getPending() is 'pending'
			if (in_array($this->test_id($elem), $memory)) {
				//simple in_array($elem, $memory) can cause false match when nodes have the same element name and attributes set
				//7. If current is already in memory, there is a microdata error; return to the step labeled loop = if the current element is already processed (i.e. in memory)
			} else {
				//8. Add current to memory. (= mark as already processed)
				$memory[] = $this->test_id($elem);
                    #print_r($elem);
				if ($elem->typer()) {
					// @ removed original $memory error check here
					$varr = $this->getNames($elem);
				}
				else {
					$varr = $elem->itemValue($this->baseUri);
				}

				//10. (If current has an property attribute specified and the element has one or more property names, then) add the element to results.
				foreach ($elem->property() as $prop) {
                    //propertyに関するタグに囲まれた中身を入れ込んでいる。
					// @changed from string $value to array $varr to handle value type, lang etc.
                					$result->properties[$prop][] = $varr;
				}
			}
		}
        //Add internal prefix //propertyと同じように入れ子になっているprefixを追記していく。
        foreach ($propprefix[1] as $prefixelem) {
            $result-> prefix = array_merge($result->prefix, $prefixelem->prefixnames);
        }

		return $result;

	}
	
	//@@ methods added by masaka ////////////////////////////
	
	/**
	 * reset structured property values to simple string
	 * @param array $items	array of item objects, i.e. $result of obj()
	 */
	function to_simple_value(&$items){
		$n = count($items);
		for($i=0; $i<$n; $i++){
			$this->to_simple_value_item($items[$i]);
		}
	}
	
	/**
	 * reset structured property values of an item to simple string
	 * @param object $item	parsed item object
	 */
	function to_simple_value_item(&$item){
		foreach(array_keys($item->properties) as $name){
			$m = count($item->properties[$name]);
			for($j=0; $j<$m; $j++){
				if(is_object($item->properties[$name][$j])){
					$this->to_simple_value_item($item->properties[$name][$j]);
				}else{
					$item->properties[$name][$j] = $item->properties[$name][$j]['value'];
				}
			}
		}
	}
	
	/**
	 * Retrieve RDFa Lite in JSON-LD format.
	 * @param boolean $use_structure	if true, return URI property value as object
	 * @return string	JSON-LD representation of the RDFa Lite 
	 */
	function jsonld($use_structure=false){
		$items = $this->obj(true);
		$lditem = array();
		$n = count($items);
		for($i=0; $i<$n; $i++){
			$lditem[] = $this->to_json_ld_item($items[$i], $use_structure);
		}
		return json_encode($lditem);
	}
	
	/**
	 * convert an item object to JSON-LD style array
	 * @param object $item	parsed item object
	 * @return array	JSON-LD format of the item
	 */
	function to_json_ld_item(&$item, $use_structure=false){
		$lditem = array();
		if($item->id) $lditem["@id"] = $item->id;
		if($item->type){
			$lditem["@type"] = (count($item->type) == 1) ? $item->type[0] : $item->type;
		}
		foreach($item->properties as $name => $varray){
			$prop = array();
			foreach($varray as $pval){
				if(is_object($pval)){
					$prop[] = $this->to_json_ld_item($pval, $use_structure);
				}else{
					// JSON-LD does not take value type e.g. "@id" for Microdata 
					if($use_structure){
						if($pval['vtype'] == 'uri'){
							$prop[]["@id"] = $pval['value'];
						}elseif(isset($pval['lang']) and $pval['lang']){
							$prop[] = array(
								"@value" => $pval['value'],
								"@language" => $pval['lang']
							);
						}elseif(isset($pval['datatype']) and $pval['datatype']){
							$prop[] = array(
								"@value" => $pval['value'],
								"@type" => $pval['datatype'],
							);
						}else{
							$prop[] = $pval['value'];
						}
					}else{
						$prop[] = $pval['value'];
					}
				}
			}
			// maybe use type vocab URI to resolve property name as abs URI
			// or set type vocab as @context for each item
			$lditem[$name] = (count($prop) == 1) ? $prop[0] : $prop;
		}
		return $lditem;
	}
	
	/**
	 * load HTML, and add <meta http-equiv...charset> to HTML in order to avoid charset error of DOMDocument. Maybe not needed in the latest version of PHP
	 * @param string $url	URL of source HTML, or base URL if $data is provided
	 * @param string $data	HTML data (optional)
	 * @return string	HTML data
	 */
	function check_meta($url, &$data){
		$darray = $data ? preg_split("/[\r\n]/", $data) : file($url, FILE_IGNORE_NEW_LINES);
		$done = false;
		$len = count($darray);
		for($i=0; $i<$len; $i++){
			if(preg_match("/<meta .*charset/", $darray[$i])){
				if(preg_match("/http-equiv/i", $darray[$i])){
					return $data ? $data : join("\n", $darray);
				}else{
					$darray[$i] = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
					$done = true;
					break;
				}
			}elseif(preg_match("/<\/head>/", $darray[$i])){
				$darray[$i] = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n</head>";
				$done = true;
				break;
			}
		}
		return ($done ? "" : "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n").join("\n", $darray);
	}
	
	/**
	 * test whether the element has id attr, and if not, generate pseudo id to check memory of property processing
	 * @param object $node	the element node to test
	 * @return string	the id attribute value of the node (possibly generated here)
	 */
	function test_id(&$node){
		if(!($id = $node->getAttribute('id'))){
			$id = '_arc2md_genid_'.$this->pseudo_id++;
			$node->setAttribute('id', $id);
		}
		return $id;
	}
}


/**
 * Extend the DOMElement class with the RDFaLite API functions.
 * original from RDFaLitePHP, modified by masaka
 * http://github.com/linclark/RDFaLitePHP
 * Copyright (c) 2011 Lin Clark
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 */
class RDFaLitePhpDOMElement extends DOMElement {

	//@@ methods from original RDFaLitePhp (with some modification) ////////////////////////////
	/**
	* Determine whether the vocab attribute is present on this element.
	*
	* @return
	*   boolean TRUE if this is an item, FALSE if it is not.
	*/
    //typeofまたはprefixがあるときはその中身を見る。
	public function typer() {
        if ($this->hasAttribute('typeof') or $this->hasAttribute('prefix')){
		return "1";
	}
    }

    /*To extract external data */
    //単にprefixがあるときのその中身を取って来ている。
	public function prefix() {
	     $prefix = $this->getAttribute('prefix');
	        if (!empty($prefix)) {
            return $this->tokenList($prefix);
                             }
        return array();
		// Return NULL instead of the empty string returned by getAttributes so we
		// can use the function for boolean tests.
	}
	/**
	* Retrieve this item's typeofs.
	*
	* @return
	*   An array of typeof tokens.
	*/
	public function typeof() {
		$typeof = $this->getAttribute('typeof');
	 if (!empty($typeof)) {
            return $this->tokenList($typeof);
                             }
        return array();
		// Return NULL instead of the empty string returned by getAttributes so we
		// can use the function for boolean tests.
	}

	/**
	* Retrieve this item's itemid.
	*
	* @param string $base	base URI to resolve relative value
	* @return
	*   A string with the itemid.
	*/
	public function itemId($base) {
		// @changed to use getResolvedAttr instead of getAttribute
		return $this->getResolvedAttr('itemid', $base);
	}

	/**
	* Retrieve this item's propertys.
	*
	* @return
	*   An array of property tokens.
	*/
	public function property() {
		$property = $this->getAttribute('property');
		if (!empty($property)) {
			return $this->tokenList($property);
		}
		return array();
	}

	/**
	* Retrieve the ids of other items which this item references.
	*
	* @return
	*   An array of ids as contained in the resource attribute.
	*/
	public function resource() {
		$resource = $this->getAttribute('resource');
		if (!empty($resource)) {
			return $this->tokenList($resource);
		}
		return array();
	}

	/**
	* Retrieve the properties. Originally properties(), but renamed to avoid confusing with $result->properties array
	*
	* @param string $baseUri	base URI to resolve relative value
	* @return
	*   An array of RDFaLitePhpDOMElements which are properties of this
	*   element.
	*/
    //ここでpropertyと同時に存在するprefixも取り出している。traverseは再帰的にタグの階層構造の奥までデータを取得している。
	public function getPending($baseUri) {
		$props = array();
        //props for prefix by Maori
        $prefixlist = array();
		if ($this->typer()) {
			$toTraverse = array($this);
			foreach ($this->resource() as $resource) {
				$children = $this->ownerDocument->xpath()->query('//*[@id="'.$resource.'"]');
				foreach($children as $child) {
					if($child->typer()){
						if(! $itemid = $child->itemId($baseUri)){
							//generate pseudo itemid as bnode
							$itemid = "_:".$child->getAttribute('id');
						}
						// @@ merge if iremref'ed item is already processed for RDF. maybe different for JSON
						if(isset($this->processedresource[$itemid])){
							$this->processedresource[$itemid]++;
							break;
						}else{
							$this->processedresource[$itemid] = 1;
							$child->setAttribute('itemid', $itemid);
						}
					}
					$this->traverse($child, $toTraverse, $props, $this,$prefixlist);
				}
			}
			while (count($toTraverse)) {
				$this->traverse($toTraverse[0], $toTraverse, $props, $this,$prefixlist);
		}
		}

//		return $props;
//List で返す。
return array($props,$prefixlist);
	}

	/**
	* Retrieve the element's value, determined by the element type.
	*
	* @param string $base	base URI to resolve relative value
	* @return array
	*   structured value with value type and lang info if the element is not an item, or $this if it is
	*   an item.
	*/
	public function itemValue($base) {
		$property = $this->property();
		//$this->is_uri = false;
		if (empty($property))
			return null;
		if ($this->typer()) {
			return $this;
		}
		// @changed return value from string to array, to add contextual info.
		// Note lang is only supported for current element lang attr, not inherited language.
		switch (strtoupper($this->tagName)) {
			case 'META':
				return array(
					'value' => $this->getAttribute('content'),
					'vtype' => 'literal',
					'lang' => $this->getAttribute('lang')
				);
			case 'AUDIO':
			case 'EMBED':
			case 'IFRAME':
			case 'IMG':
			case 'SOURCE':
			case 'TRACK':
			case 'VIDEO':
				// @changed to use getResolvedAttr instead of getAttribute
				return array(
					'value' => $this->getResolvedAttr('src', $base),
					'vtype' => 'uri'
				);
			case 'A':
			case 'AREA':
			case 'LINK':
				// @changed to use getResolvedAttr instead of getAttribute
				return array(
					'value' => $this->getResolvedAttr('href', $base),
					'vtype' => 'uri'
				);
			case 'OBJECT':
				// @changed to use getResolvedAttr instead of getAttribute
				return array(
					'value' => $this->getResolvedAttr('data', $base),
					'vtype' => 'uri'
				);
			case 'DATA':
				return array(
					'value' => $this->getAttribute('value'),
					'vtype' => 'literal',
					'lang' => $this->getAttribute('lang')
				);
			case 'TIME':
				return array(
					'value' => ($this->hasAttribute('datetime') ?
						$this->getAttribute('datetime') : $this->textContent),
					'vtype' => 'literal',
					'elt_type' => 'time' //record element to determine datatype
				);
			default:
				return array(
					//Note this might cause XSS problem if the result will directly put into HTML
					'value' => $this->textContent, 
					'vtype' => 'literal',
					'lang' => $this->getAttribute('lang')
				);
		}
	}

	/**
	* Parse space-separated tokens into an array.
	*
	* @param string $string
	*   A space-separated list of tokens.
	* @param boolean $check_absUri
	*   to check when token must be abs uri, e.g. typeof
	* @return array
	*   An array of tokens.
	*/
	protected function tokenList($string, $check_absUri=false) {
		//return explode(' ', trim($string));
		$tokens = explode(' ', trim($string));
		if($check_absUri and count($tokens)){
			$ok_tokens = array();
			foreach($tokens as $token){
				if(preg_match("/^\w+:/", $token)) $ok_tokens[] = $token;
			}
			return $ok_tokens;
		}else{
			return $tokens;
		}
	}

	/**
	* Traverse the tree.
	* 
	* In RDFaLiteJS, this is handled using a closure.
	* See comment for RDFaLitePhp:getObject() for an explanation of closure use
	* in this library.
	*/
    //ここで再帰的にpropertyとprefixをもれなく取り出している。prefixはlistとしてまとめている。
	protected function traverse($node, &$toTraverse, &$props, $root, &$prefixlist) {
		foreach ($toTraverse as $i => $elem)  {
			if ($elem->isSameNode($node)){
				unset($toTraverse[$i]);
			}
		}
		if (!$root->isSameNode($node)) {
			$names = $node->property();
            $prefixnames = $node->prefix();
           if ($prefixnames){
$prefixprops->prefixnames = $prefixnames;
array_push($prefixlist,$prefixprops);
return $prefixlist;
           }
           
			if (count($names)) {
				//@todo Add support for property name filtering.
				$props[] = $node;
			}
			if ($node->typer()) {
				return;
			}
		}
		if (isset($node)) {
			// An xpath expression is used to get children instead of childNodes
			// because childNodes contains DOMText children as well, which breaks on
			// the call to getAttributes() in property().
			$children = $this->ownerDocument->xpath()->query($node->getNodePath() . '/*'); //*/
			foreach ($children as $child) {
				$this->traverse($child, $toTraverse, $props, $root, $prefixlist);
			}
		}
	}


	//@@ methods added by masaka ////////////////////////////
	/**
	 * instead of simple getAttribute, resolved URI is returned for href type attr
	 * @param string $attrname	name of the attribute to get value
	 * @return string	absolute URI, or null if not found
	 */
	function getResolvedAttr($attrname, $base){
		return $this->hasAttribute($attrname) ? 
			$this->resolveUri($this->getAttribute($attrname), $base) :
			null;
	}
	
	/**
	 * resolve relative URI against base URI so that itemids and item values have absolute URI
	 * @param string $relpath	relative URI
	 * @param string $base	base URI against which the relative URI to be resolved
	 * @return string	resolved absolute URI
	 */
	function resolveUri($relpath, $base){
		// empty path = base uri itself
		if($relpath == '') return $base;
		// special treatment for bnodeId
		if(substr($relpath, 0, 2)=='_:') return $relpath;
		$r = parse_url($relpath);
		// if scheme present, relpath is actually absolute URI
		if(isset($r['scheme'])) return $relpath;
		// get Base URI Components
		$buc = parse_url($base);
		// $t... is transform reference
		$thead = $buc['scheme'] . '://' . $buc['host'] . (isset($buc['port']) ? ':'.$buc['port'] : '');
		// set $rpq to path + query
		$rpq = $r['path'] . (isset($r['query']) ? '?'.$r['query'] : '');
		if(preg_match('!^/.*!', $rpq)) {
			// server root relative
			$turi = $thead . $rpq;
		}elseif(empty($r['path'])){
			// empty path component = query or fragment only
			$turi = $thead . $buc['path'] . (isset($r['query']) ? '?'.$r['query'] : '');
		}else{
			// transform path components (initially set from base)
			$tpc = explode('/', $buc['path']);
			array_pop($tpc); // last segment (=file name) of base is ignored
			// relative path component
			$rpc = explode('/', $rpq);
			// resolve for each segment of relative path component
			foreach($rpc as $rdseg){
				if($rdseg == '.'){
					array_shift($tpc);
					array_unshift($tpc, '');
				}elseif($rdseg == '..'){
					array_pop($tpc);
					if(count($tpc) == 0) $tpc = array('');
				}else{
					array_push($tpc, $rdseg);
				}
			}
			$turi = $thead . implode ('/', $tpc);
		}
		return $turi .(isset($r['fragment']) ? '#'.$r['fragment'] : '');
	}
	
}

/**
 * Extend the DOMDocument class with the RDFaLite API functions.
 * original from RDFaLitePHP
 * http://github.com/linclark/RDFaLitePHP
 * Copyright (c) 2011 Lin Clark
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 */
class RDFaLitePhpDOMDocument extends DOMDocument {
	/**
	* Retrieves a list of microdata items.
	*
	* @return
	*   A DOMNodeList containing all top level microdata items.
	*
	* @todo Allow restriction by type string.
	*/
	public function getItems() {
		// Return top level items.
		return $this->xpath()->query('//*[@typeof or @prefix and not(@property)]');
	}

	/**
	* Creates a DOMXPath to query this document.
	*
	* @return
	*   DOMXPath object.
	*/
	public function xpath() {
		return new DOMXPath($this);
	}
}
?>
