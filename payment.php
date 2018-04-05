<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__) . '/nockscheckout.php');

$nockscheckout = new nockscheckout();

Tools::redirect(Context::getContext()->link->getModuleLink('nockscheckout', 'payment'));
