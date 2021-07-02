<?php

require_once( JPATH_ROOT . 'class.wp-wrapper-api2.php' ); 
       

	//Source system objects
    //$post
    //$category
    //$author
	$blog = array();
	$blog['title']      	= preg_replace('!\s+!', ' ', $post->title);
	$blog['description']    = $post->intro.$post->content;
	$blog['status']    	= 'future';
    $blog['date_gmt']       = date_create(gmdate('Y-m-d H:i:s'))->modify("+1 minutes")->format('Y-m-d H:i:s');
	$blog['username']   	=  $row->wp_username;
	$blog['userpassword']   = $row->wp_password;
	$blog['authorname']   	= $author->name;
	$blog['emailid']        = $author->email;
	$blog['category']   	= $category->title;
	$blog['baseurl']   	= str_replace("\/","/",str_replace("\/\/","//",trim($website,'"')));
	$blog['tags']		= $postTags;

	$image_string = substr($post->image, 0, 5);
	if($image_string=="http:" || $image_string=="https")
	{
		$blog['img_url'] = $post->image;
	}
	else if($image_string=="post:" || $image_string=="user:")
	{
		$blog['img_url']  = $post->getImage('large');
	}
	
    if(empty($blog['img_url']))
	{
	    if(preg_match_all('/<img\s+.*?src=[^>]+>/i',$blog['description'], $result) > 0){
	   	if( preg_match( '@src="([^"]+)"@' , $result[0][0], $match ) > 0 )
	   	{
	           $blog['img_url'] = array_pop($match);
		       $blog['description'] = preg_replace('/<img.*?>/', '', $blog['description'], 1);
			}
	   }
	   else //
	   {
		$str1 = stripslashes($blog['description']);
		preg_match('/<iframe.*src="(.*)".*><\/iframe>/isU', $str1, $matches);

		if(!empty($matches) && count($matches) > 0)
		{
            preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', array_pop($matches), $match);
			//$youtube_id = $match[1];	
			$youtube_id = array_pop($match);
            if(!empty($youtube_id))
            {
				$youtube_img = "https://img.youtube.com/vi/".$youtube_id."/0.jpg";
				$blog['img_url'] = $youtube_img;
				$post->image = $youtube_img;
            }
		}
	   }
	}
	else
	{
	   $pattern 	= '/[\w\-]+\.(jpg|png|gif|jpeg)/';
	   $result1 	= preg_match($pattern, $blog['img_url'], $matches1);
	   $coverImage  = $matches1[0];

	   $result 	= preg_match($pattern, $blog['description'], $matches);
	   $contentImg  = $matches[0];  

	   //$item->description = $itemHtml; 
	   if($contentImg == $coverImage){
	        $blog['description'] = preg_replace('/<img.*?>/', '', $blog['description'], 1);
	   }
	}

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
        /*if (json_last_error() !== 0) {
		$result = $obj->__getPost();
		if($result == false)
		{
		   continue;
		}
		$get = $result;                                           
                           }*/
        }
	
    if(!empty($get->id)){
        $wpid = $get->id;
        $permalink = $get->slug;
	}
	else
	{
	  $permalink = $obj->sanitize_permalink($blog['title']);
    }
