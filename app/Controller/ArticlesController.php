<?php

class ArticlesController extends AppController {

	public $uses = ['Article'];
	public function index() {
		$data = ['name'=>'Hi','message'=>'Hello'];
		$this->Article->create($data);
		$this->Article->save($data);
		pr($this->Article->read(null,"6051711999279104"));
		
	}

}
