<?php

/*
Copyright (C) 2011 by David Eriksson <david@2good.nu>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */



/**
 * Base class for anything that can be injected in an object
 */
abstract class Pebble
{
  private $name;

  public function __construct($name)
  {
    $this->name = $name;
  }

  public function getName()
  {
    return $this->name;
  }

  /**
   * Implement this method in subclass to return value of Pebble
   */
  public abstract function getValue();

  /**
   * Helper that reads PebbleFactory and SharedPebble annotations for a class 
   * and creates a corresponding Pebble object
   */
  public static function fromClassAnnotation($className)
  {
    $class = new ReflectionClass($className);
    $annotationAndName = self::getAnnotationAndName($class, 
      array('PebbleFactory','SharedPebble'));
    if (empty($annotationAndName))
      return null;
    list($implementation, $name) = $annotationAndName;
    return new $implementation($name, $className); 
  }

  /**
   * Helper that reads annotation name and value from a Reflector (class or property)
   * If name is not provided in annotation, use name from Reflector
   */
  public static function getAnnotationAndName(Reflector $reflector, array $validAnnotations)
  {
    $docComment = $reflector->getDocComment();
    if (empty($docComment))
      return null;

    $matches = array();
    if (preg_match('/@('.implode('|', $validAnnotations).')\s*(\(([^)]*)\)|)/i', $docComment, $matches) == 0)
      return null;
    
    $annotation = $matches[1];
    @$name = $matches[3];

    if (empty($name))
    {
      $name = $reflector->getName();
    }

    return array($annotation, trim($name));
  }

}

/**
 * This type of Pebble will create a new object of the specific class every time
 */
class PebbleFactory extends Pebble
{
  private $className;

  public function __construct($name, $className)
  {
    parent::__construct($name);
    $this->className = $className;
  }

  /**
   * Create a new object of this class and inject dependencies
   */
  public function newInstance()
  {
    return PebbleDash::dashObject(new $this->className);
  }

  public function getValue()
  {
    return $this->newInstance();
  }
}

/**
 * This type will return the same object every time
 */
class SharedPebble extends PebbleFactory
{
  private $instance = null;
  
  public function __construct($name, $className)
  {
    parent::__construct($name, $className);
  }

  /**
   * Singleton value
   */
  public function getValue()
  {
    if (empty($this->instance))
    {
      $this->instance = $this->newInstance();
    }
    return $this->instance;
  }
}

/**
 * This type will simply return the value
 */
class ValuePebble extends Pebble
{
  private $value;

  public function __construct($name, $value)
  {
    parent::__construct($name);
    $this->value = $value;
  }

  public function getValue()
  {
    return $this->value;
  }
}

/**
 * This very static class maps all pebbles by name
 */
class PebbleCollection
{
  private static $pebbles = null;

  /**
   * Initialize collection by looking for annotations on all classes
   */
  public static function collectPebbles()
  {
    if (self::$pebbles === null)
    {
      self::$pebbles = array();

      $declaredClasses = get_declared_classes();
      foreach ($declaredClasses as $className)
      {
        self::setFromClassAnnotation($className);
      }
    }

    return self::$pebbles;
  }

  /**
   * Put a Pebble in the collection
   */
  public static function setPebble($pebble)
  {
    if ($pebble)
    {
      self::$pebbles[strtolower($pebble->getName())] = $pebble;
    }
  }

  /**
   * Get a Pebble from the collection, but please use getValue instead.
   */
  public static function getPebble($name)
  {
    $pebbles = self::collectPebbles();
    return $pebbles[strtolower($name)];
  }

  /**
   * Examine a class and if it has a suitable annotation it will be added to the collection
   */
  public static function setFromClassAnnotation($className)
  {
    self::setPebble(Pebble::fromClassAnnotation($className));
  }

  /**
   * Create a PebbleFactory regardless of annotation
   */
  public static function setFactory($name, $className)
  {
    self::setPebble(new PebbleFactory($name, $className));
  }

  /**
   * Create a SharedPebble regardless of annotation
   */
  public static function setShared($name, $className)
  {
    self::setPebble(new SharedPebble($name, $className));
  }


  /**
   * Store any value in the collection
   */
  public static function setValue($name, $value)
  {
    self::$pebbles[strtolower($name)] = new ValuePebble($name, $value);
  }

  /**
   * Get value of the named pebble
   */
  public static function getValue($name)
  {
    $pebble = self::getPebble($name);
    if (empty($pebble))
      return null;
    return $pebble->getValue();
  }


}


/**
 * Usage method 1: Inherit from PebbleDash
 *
 * Usage method 2: Call PebbleDash::dashObject($this) from __construct in your class
 */
abstract class PebbleDash
{
  /**
   * Inject dependency on all annoted properties 
   */
  public static function dashObject($instance)
  {
    if (@$instance->_pebbleDash)
      return;

    $propertyFilter = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE;

    $object = new ReflectionObject($instance);
    $properties = $object->getProperties($propertyFilter);
    
    foreach ($properties as $property)
    {
      self::dashProperty($instance, $property);
    }

    $instance->_pebbleDash = true;

    return $instance;
  }

  /**
   * Inject dependency on a specific property. Used by dashObject.
   */
  public static function dashProperty($instance, $property)
  {
    $annotationAndName = Pebble::getAnnotationAndName($property, array('Pebble'));
    if (empty($annotationAndName))
      return;
    $name = $annotationAndName[1];

    $property->setAccessible(true);
    $property->setValue($instance, PebbleCollection::getValue($name));
  }

  /**
   * Don't forget to call parent::__construct() in your subclass...
   */
  public function __construct()
  {
    self::dashObject($this);
  }

}

