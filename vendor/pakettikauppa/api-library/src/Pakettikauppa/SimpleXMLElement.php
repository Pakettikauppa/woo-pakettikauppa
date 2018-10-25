<?php

namespace Pakettikauppa;

class SimpleXMLElement extends \SimpleXMLElement
{
  /**
   * Escapes input text
   *
   * @param string
   * @param string
   */
  public function addChild($key, $value = null, $namespace = null)
  {
    if ( $value != null )
    {
      $value = htmlspecialchars($value, ENT_XML1);
    }

    return parent::addChild($key, $value, $namespace);
  }
}
