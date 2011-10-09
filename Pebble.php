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
 * Usage method 1: Inherit from Pebble
 *
 * Usage method 2: Call Pebble::dash($this) from __construct in your class
 */
abstract class Pebble
{
  /**
   * Inject dependency on all annotated properties 
   *
   * @param object $instance An object we will search for @Pebble annotations
   *
   * @return object Returns $instance
   */
  public static function dash($instance)
  {
    if (@$instance->_pebbleDash)
      return $instance;
    $instance->_pebbleDash = true;

    $propertyFilter = ReflectionProperty::IS_PUBLIC | 
      ReflectionProperty::IS_PROTECTED | 
      ReflectionProperty::IS_PRIVATE;

    $object = new ReflectionObject($instance);
    $properties = $object->getProperties($propertyFilter);
    
    foreach ($properties as $property)
    {
      self::dashProperty($instance, $property);
    }

    return $instance;
  }

  /**
   * Inject dependency on a specific property. Used by dashObject.
   */
  private static function dashProperty($instance, $property)
  {
    $annotation = PebbleAnnotation::parse($property, array('Pebble'));
    if (empty($annotation))
      return;

    $value = PebbleCollection::get($annotation->getValue());

    if (is_object($value))
    {
      Pebble::dash($value);
    }

    $property->setAccessible(true);
    $property->setValue($instance, $value);
  }

  /**
   * Don't forget to call parent::__construct() in your subclass...
   */
  public function __construct()
  {
    self::dash($this);
  }
}


/**
 * This very static class maps all pebbles by name
 */
class PebbleCollection
{
  private static $pebbles = null;

  /**
   * Get value in the collection
   *
   * @param  string  $name  Pebble name. Case insensitive.
   *
   * @return  mixed  Pebble value.
   */
  public static function get($name)
  {
    $name = strtolower($name);
    if (!array_key_exists($name, self::$pebbles)) 
    {
      throw new InvalidArgumentException('Unknown pebble: '.$name);
    }

    $value = self::$pebbles[$name];
    return $value instanceof Closure ?  $value() : $value;
  }

  /**
   * Store pebble in the collection
   *
   * @param  string  $name  Pebble name. Case insensitive.
   * @param  mixed   $value Either a Closure or a value.
   */
  public static function set($name, $value)
  {
    self::$pebbles[strtolower($name)] = $value;
  }

  /**
   * Set a closure that is only called once. It will always return the 
   * same value.
   */
  public static function once($name, Closure $callable)
  {
    self::set(
      $name, function () use ($name, $callable) {
        $instance = $callable();
        PebbleCollection::set($name, $instance);
        return $instance;
      }
    );
  }
}

/**
 * Helper class for handling annotations
 */
class PebbleAnnotation
{
  private $name;
  private $value;

  private function __construct($name, $value)
  {
    $this->name = $name;
    $this->value = $value;
  }

  public function getName()
  {
    return $this->name;
  }

  public function getValue()
  {
    return $this->value;
  }

  /**
   * Helper that reads annotation name and value from a Reflector (class or 
   * property)
   * If value is not provided in annotation, use name from Reflector
   */
  public static function parse(Reflector $reflector, array $validAnnotations)
  {
    $docComment = $reflector->getDocComment();
    if (empty($docComment))
      return null;

    $matches = array();
    if (preg_match('/@('.implode('|', $validAnnotations).')\s*(\(([^)]*)\)|)/i', $docComment, $matches) == 0)
      return null;
    
    $name = $matches[1];
    @$value = $matches[3];

    if (empty($value))
    {
      $value = $reflector->getName();
    }

    return new self($name, trim($value));
  }


}
