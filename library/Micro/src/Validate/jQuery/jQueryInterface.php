<?php
namespace Micro\Validate\jQuery;

interface jQueryInterface {
   /* EXAMPLE
    public function jqValidate ()
    {


        $js .= sprintf("%s.validator.addMethod('%s',function(value, element) {
                    var regex = new RegExp(\"%s\");
                    return this.optional( element ) || regex.test( value );},'%s')",
                ZendX_JQuery_View_Helper_JQuery::getJQueryHandler(),
                $this->getName(),
                $this->getPattern(),
                $this->_messageTemplates[self::INVALID]
        );

        return $js;

    }
    */

    public function jqValidate();
    public function getJQMethod();
}