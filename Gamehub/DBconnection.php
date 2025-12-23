<?php
$servername="localhost";
$username="root";
$password="";
$dbname="gamehub";

$conn = new mysqli($servername,$username,$password);

if($conn->connect_error)
{
	die("Connetion failed: " . $conn->connect_error);
}
else
{
	echo "Connection successful";
}
?>