<?php
require 'auth.php';
session_destroy();
header("Location: index.php");
