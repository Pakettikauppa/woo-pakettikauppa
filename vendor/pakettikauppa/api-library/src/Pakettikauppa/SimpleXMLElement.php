<?php

namespace Pakettikauppa;

class SimpleXMLElement extends \SimpleXMLElement
{
  /**
   * Escapes input text
   *
   * @param string
   * @param string|null
   * @param string|null
   */
  public function addChild(string $qualifiedName, ?string $value = null, ?string $namespace = null): ?SimpleXMLElement
  {
    if ( $value != null )
    {
      $value = htmlspecialchars($value, ENT_XML1);
    }

    return parent::addChild($qualifiedName, $value, $namespace);
  }
}
