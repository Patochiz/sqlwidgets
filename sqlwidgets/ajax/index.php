<?php
// Direct access forbidden
if (!defined('NOTOKENRENEWAL') && !defined('NOREQUIREMENU')) {
    header('Forbidden');
    exit;
}