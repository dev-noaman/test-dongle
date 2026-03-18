<?php
/**
 * FreePBX Dongle Manager Module - Page Entry Point
 *
 * This file is the main entry point for the module's web interface.
 * FreePBX routes requests to this file based on the module.xml menuitems.
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Get the module instance and show the page
$module = \FreePBX::Donglemanager();
$module->showPage();
