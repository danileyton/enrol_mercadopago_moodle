<?php
// This file is part of Moodle - http://moodle.org/
//
// MercadoPago enrolment plugin for Moodle 5.0+
// @package    enrol_mercadopago
// @copyright  2025
// @author     Hernan Arregoces / Adaptado por EpicChile
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();
// Component name (frankly the plugin¡¯s unique identifier)
$plugin->component = 'enrol_mercadopago';
// Version number (used for upgrades; must match install.xml and upgrade.php)
$plugin->version   = 2025012800;
// Required Moodle core version (5.0 or later)
$plugin->requires  = 2024100700;
// Maturity level: ALPHA (dev), BETA, RC (release candidate), STABLE
$plugin->maturity  = MATURITY_RC;
// Human-readable release string
$plugin->release   = '3.7.0 (SDK v3, Moodle 5.x)';
// Minimum PHP version
$plugin->php       = '8.2.0';