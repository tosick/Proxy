<?php

class App {

	static $lines_per_page = 10;

	public function getData() {

		$defaults = [
			'area' => 0,
			'birth' => '',
			'patron' => '',				
			'name' => '',
			'fam' => '',
		];

		$config = Flight::get('config');

		$query = array_merge($defaults, array_diff_key($_GET, [ 'auth' => NULL ]));

		$response = json_decode(Http::getData($query), true);

		if (isset($response['error']) && !$response['error']) {

			$stm = Flight::db()->prepare("
				INSERT INTO  getdata (`login`,`fam`, `name`, `patron`, `date_birth`, `date_ins`, `area`, `request_ok`)
				VALUES ('',:fam, :name, :patron, :birth, CURRENT_DATE(), :area, :status);
			");

			$stm->bindValue('fam',    $query['fam'],        PDO::PARAM_STR);
			$stm->bindValue('name',   $query['name'],       PDO::PARAM_STR);
			$stm->bindValue('patron', $query['patron'],     PDO::PARAM_STR);
			$stm->bindValue('birth',  self::correctDate($query['birth']),      PDO::PARAM_STR);
			$stm->bindValue('area',   $query['area'],       PDO::PARAM_INT);
			$stm->bindValue('status', $response['error'] ? 0 : 1,  PDO::PARAM_INT);

			$stm->execute();

			if (isset($config['fields'])) {
				$response = self::trnaslateFields($response, $config['fields']);
			}			

			Flight::json($response);
		} else Flight::json(Http::error);
	}

	public function json($data, $code = 200, $encode = false, $charset = 'utf-8') {
		Flight::json(json_encode($data, JSON_UNESCAPED_UNICODE), $code, $encode, $charset);
	}

	public function getAllData() {
		$stm = Flight::db()->prepare("
			SELECT d.fam, d.name, d.fam, d.patron, d.date_birth, d.request_ok, r.name AS `region`
			FROM `getdata` AS `d`
			LEFT JOIN `regions` AS `r` ON `r`.`id` = `d`.`area`
		");
		$stm->execute();

	    $data =  $stm->fetchAll(PDO::FETCH_ASSOC);
	    App::json($data);			
	}

	private function correctDate($date) {
		$dt_parts = explode('/', $date);
		if (count($dt_parts) == 3) {
			return implode('-', array_reverse($dt_parts));
		}
		return '0000-00-00';
	}

	public function viewData() {

		$query = Flight::request()->query;
		if (isset($query['page'])) {
			$page = $query['page'];
		} else $page = 1;

		$stm = Flight::db()->prepare("
			SELECT SQL_CALC_FOUND_ROWS d.fam, d.name, d.fam, d.patron, d.date_birth, d.request_ok, r.name AS `region`
			FROM `getdata` AS `d`
			LEFT JOIN `regions` AS `r` ON `r`.`id` = `d`.`area`
			LIMIT :offset, :count
		");
		$stm->bindValue('offset', ($page - 1) * self::$lines_per_page, PDO::PARAM_INT);
		$stm->bindValue('count', self::$lines_per_page, PDO::PARAM_INT);
		$stm->execute();
	    $data = $stm->fetchAll(PDO::FETCH_ASSOC);

	    $rs = Flight::db()->query("SELECT FOUND_ROWS()");
	    $count = $rs->fetchColumn();

	    $variables = [
	    	'title'  => 'Dashboard',
	    	'data'   => $data,
	    	'page'   => $page,
	    	'limit'  => self::$lines_per_page,
	    	'count'  => $count,
	    	'offset' => ($page - 1) * self::$lines_per_page,
    	 ];

	    Flight::renderBody('body', $variables);
	}

	public function debug($var)	{
		die('<pre>'. print_r($var, true) .'</pre>');
	}

	public function serverInfo() {
		Flight::renderBody('server', [ 'data' => $_SERVER, 'title' => 'Server variables' ]);
	}

	private function trnaslateFields(&$response, $fields) {
		function translate($item) {
			$newItem = array();
			foreach ($fields as $key => $field) {
				$newItem[$field] = $item[$key];
			}
			return $newItem;
		}
		$response['data'] = array_map('translate', $response['data']);
	}
}