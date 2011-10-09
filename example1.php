<?php

require_once 'Pebble.php';
require_once 'PebbleCollector.php';



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



class Master extends Pebble
{
  /** @Pebble */
  private $foo;

  public function run()
  {
    $this->foo->run();
  }

}

PebbleCollector::collectPebbles();

$master = new Master();
$master->run();

