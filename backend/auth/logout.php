<?php
require_once __DIR__ . '/../helpers/response.php';
setApiHeaders();
session_destroy();
jsonOk(['message' => 'Logged out.']);
