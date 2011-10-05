<?php

require_once 'pebble.php';



/** @PebbleFactory() */
class SomeBar
{

  public function run()
  {
    echo "Bar!\n";
  }
}


/** @SharedPebble (foo) */
class SomeFoo
{
  /** @Pebble(someBar ) */
  private $bar;


  public function run()
  {
    echo "Foo!\n";
    $this->bar->run();
  }
}



class Master extends PebbleDash
{
  /** @Pebble */
  private $foo;

  public function run()
  {
    $this->foo->run();
  }

}


$master = new Master();
$master->run();

