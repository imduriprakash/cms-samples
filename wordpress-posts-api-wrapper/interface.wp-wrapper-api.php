<?php 

interface Common_wrapper{

public function __send($url, $method, $post_fields, $username, $password);

public function get_file_name($path);

public function slug_text($str);

public function random_password($length);

}

?>