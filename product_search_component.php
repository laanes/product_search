<?php 

class Product_Search_Component 
{
	
	var $pattern = "/([a-zA-Z0-9\.])+-([a-zA-Z0-9_-])+/";
	var $where_condition;
	var $indexes;
	var $needle;
	var $product_results;
	var $search_array;
	var $search_small;
	var $search_word;
	var $noKeys;
	var $noKeys2;

	public function __construct( $needle ) {
		
	$this->needle = $needle;
	$this->filter();
	$this->go_bear();
	$this->get_results();

	}

	private function filter() {

	$this->needle = trim(preg_replace(array('#^or\s#i','#^and\s#i'),'', $this->needle));
	#:dazza:# Relevant Search

		if(preg_match('#(^haf)[\d(\.)]+#i', $this->needle, $hafele_matches)) {

		$this->needle = trim(str_replace(array("$hafele_matches[1]", "$hafele_matches[2]"),'',$this->needle));

		}

		if(preg_match_all('#\.#', $this->needle, $matches) == 2 && !preg_match('#haf#i', $this->needle)) {

		$this->needle = trim(preg_replace('#\.#','', $this->needle));

		}

	}

	private function search_small() {

	$searchwords = split ( "[ ,]", sanitizeVar($this->needle));   

		foreach ($searchwords as $word) {
			
			$search_array[] = $word;
			if (strlen($word)>3) {
			$search_word[] = $word;
			}
			if (strlen($word)<4) {
			$search_small[] = $word;
			}
			preg_match($this->pattern, $word, $wordmatches);
			if ($wordmatches == TRUE && (strlen($word)<5)){
			$search_small[] = $word;
			}

		}

		$this->search_array = $this->search_array;
		$this->search_small = $search_small;
		$this->search_word  = $search_word;

	}

	private function set_no_keys() {
	
		$this->noKeys2 = count($this->search_small);
		
		$this->noKeys = count($this->search_array);

		$this->noKeys1 = $this->noKeys - $this->noKeys2;
	
	}

	private function go_bear() {

	global $db;
		
    preg_match($this->pattern, $this->needle, $matches);

	if ($matches == TRUE){
	
	if (strlen($matches[0])>4) {
	$this->needle = str_replace("$matches[0]", "\"$matches[0]\"", $this->needle);
	}}
	$this->needle = strtoupper($this->needle);
	if (!isset($_GET['sort_by'])) {
	unset($_GET['Submit'],$orderSort);
	}

	$this->set_no_keys();

	if ($this->noKeys2 == TRUE) {

	$like = build_like();

	}

	$searchQuery = "SELECT id FROM ".$glob['dbprefix']."CubeCart_search WHERE searchstr=".$db->mySQLsafe($this->needle)."";
	$searchLogs = $db->select($searchQuery);
					
	$insertStr['searchstr'] = $db->mySQLsafe($this->needle);
	$insertStr['hits'] = $db->mySQLsafe(1);
	$updateStr['hits'] = "hits+1";
					
	if ($searchLogs) {
		$counted = $db->update($glob['dbprefix']."CubeCart_search", $updateStr,"id=".$searchLogs[0]['id'],$quote = "");
	} else if (!empty($this->needle)) {
		$counted = $db->insert($glob['dbprefix']."CubeCart_search", $insertStr);
	}
	
	$this->indexes = $db->getFulltextIndex('inventory', 'I');
	
	$where = $this->build_where_cond();

	$this->where_condition = sprintf('AND %s%s', implode(' AND ', $where), $like);
	
	}

	private function build_like() {
		
		$like = '';

		for ($i=0; $i<$this->noKeys2; $i++) {

			$ucSearchTerm = $this->search_small[$i];

			if ($ucSearchTerm == TRUE){

			$like .= "AND (I.name LIKE '%".$this->search_small[$i]."%' OR I.description LIKE '%".$this->search_small[$i]."%')";

			}

			}
		
		return $like;		

	}

	private function build_where_cond() {
		
	if (!empty($_REQUEST['priceMin']) && is_numeric($_REQUEST['priceMin'])) $where[] = sprintf("I.price >= %s", number_format($_REQUEST['priceMin']/$currencyVars[0]['value'], 2, '.', ''));
	if (!empty($_REQUEST['priceMax']) && is_numeric($_REQUEST['priceMax'])) $where[] = sprintf("I.price <= %s", number_format($_REQUEST['priceMax']/$currencyVars[0]['value'], 2, '.', ''));
	
	if (isset($_REQUEST['inStock'])) $where[] = "((I.useStockLevel = 0) OR (I.useStockLevel = 1 AND I.stock_level > 0))";
	
	if (!empty($_REQUEST['category'])) {
		if (is_array($_REQUEST['category'])) {
			foreach ($_REQUEST['category'] as $cat_id) {
				if (is_numeric($cat_id)) $cats[] = $cat_id;
			}
			if (!empty($cats)) $where[] = sprintf("I.cat_id IN (%s)", implode(',', $cats));
		} else if (is_numeric($_REQUEST['category'])) {
			$where[] = sprintf("I.cat_id = '%d'", $db->mySQLsafe($_REQUEST['category']));
		}
	}

	$where[] = "C.cat_id = I.cat_id";
	$where[] = "C.hide = '0'";
	$where[] = "(C.cat_desc != '##HIDDEN##' OR C.cat_desc IS NULL)";
	$where[] = "I.disabled = '0'";

	return $where;

	}

	private function get_results() {

	global $db;

		if(is_array($this->indexes)) {
		sort($this->indexes);

		$mode = ' IN BOOLEAN MODE';

		if (!empty($this->needle)) {

		if (empty($orderSort)) {
		$orderSort = " ORDER BY productCode ASC";
		}
		$matchString = sprintf(" (0.9 * ( MATCH (%s) AGAINST(%s%s)) + (0.2 * (MATCH (I.description) AGAINST (%2\$s%3\$s))))", "I.name", $db->mySQLsafe($this->needle), $mode); 	

		$matchString1 = sprintf(" MATCH (%s) AGAINST(%s%s)", implode(',', $this->indexes), $db->mySQLsafe($this->needle), $mode); 



		$search = sprintf("SELECT DISTINCT(I.productId), I.*, %2\$s AS SearchScore FROM %1\$sCubeCart_inventory AS I , %1\$sCubeCart_category AS C WHERE (%6\$s) >= %4\$s ".$prod_filters." AND C.cat_id > 0 %3\$s %5\$s", $glob['dbprefix'], $matchString, $this->where_condition, $this->noKeys1, $orderSort, $matchString1);

		} 

		else {

		$search = sprintf("SELECT DISTINCT(I.productId), I.* FROM %1\$sCubeCart_inventory AS I, %1\$sCubeCart_category AS C WHERE I.cat_id > 0 %2\$s %3\$s", $glob['dbprefix'], $this->where_condition, $orderSort);

		}	

		$productListQuery = $search;

		$this->product_results = $db->select($productListQuery, $config['productPages'], $page);

		}

	}

}

?>