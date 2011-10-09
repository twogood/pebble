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
 * Helper class for reading annotations on classes
 */
class PebbleCollector
{
  /**
   * Collect pebbles and store in PebbleCollection
   */
  public static function collectPebbles()
  {
    $declaredClasses = get_declared_classes();
    foreach ($declaredClasses as $className)
    {
      self::setFromClassAnnotation($className);
    }
  }

  /**
   * Examine a class and if it has a suitable annotation it will be 
   * added to the collection
   */
  private static function setFromClassAnnotation($className)
  {
    $annotation = self::getClassAnnotation($className);
    if (empty($annotation))
      return;

    $value = function() use ($className) {
      return new $className();
    };

    switch ($annotation->getName())
    {
    case 'PebbleFactory':
      PebbleCollection::set($annotation->getValue(), $value);
      break;

    case 'SharedPebble':
      PebbleCollection::once($annotation->getValue(), $value);
      break;
    }
  }

  private static function getClassAnnotation($className)
  {
    return PebbleAnnotation::parse(new ReflectionClass($className), 
    array('PebbleFactory','SharedPebble'));
  }

}
