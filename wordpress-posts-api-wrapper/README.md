# Wordpress REST API wrapper
The API wrapper helps to simplify usage of Wordpress REST API,
you can send Author, Category, Terms details, it will create in your wordpress CMS and then pass the new Ids/metaids to Create post API.

# Usage
```
<?php

require_once( JPATH_ROOT . 'class.wp-wrapper-api2.php' ); 
	$obj = new WP_wrapper_api($blog);
	$result = $obj->__createPost();
                        //print_r($result);exit;
    if(substr($result, 0, 5) == "Error"){
        //check if the post might have created
		$result = $obj->__getPost();
		if($result == false)
		{
		   continue;
		}
		$get = $result;
    }
	else
        {
            $get = json_decode($result);      
        }
	
    if(!empty($get->id)){
        $wpid = $get->id;
        $permalink = $get->slug;
	}
  ```
