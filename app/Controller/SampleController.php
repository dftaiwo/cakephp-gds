<?php

class SampleController extends AppController {

	public $uses = ['Book'];

	function index() {
		$conditions=['Book.id'=>"6122080743456768"];
		pr($this->Book->find('first',compact('conditions','order','limit','fields')));
	}

}
