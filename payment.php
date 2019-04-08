<?php

include(dirname(__FILE__).'/../../config/config.inc.php');

Tools::redirect(Context::getContext()->link->getModuleLink('nockscheckout', 'payment', $_GET));
