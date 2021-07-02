<?php
require('interface.wp-wrapper-api.php');
class WP_wrapper_api implements Common_wrapper{

	var $baseurl;
	var $title;
	var $content;
	var $status;
	var $categories ;
	var $tags;
	var $image_url;
	var $alt_text;
	var $caption;
	var $description;
	var $authorname;
	var $emailid;	
	var $post_field 	= array();
	var $cat_id_arr 	= array();
	var $tag_id_arr		= array();
	var $author_id;
	var $featured_img_id;

	var $username;
	var $password;

	public function __Construct($post_arr = NULL){

		$this->baseurl 		= rtrim($post_arr['baseurl'], '/\\');
		$this->title 		= trim($post_arr['title']);
		$this->content 		= trim($post_arr['description']);
		$this->categories 	= trim($post_arr['category']); // Comma seperated string
		$this->tags 		= trim($post_arr['tags']); // Comma seperated string
		$this->image_url 	= trim($post_arr['img_url']);
		$this->alt_text		= trim($post_arr['img_alt']);
		$this->caption 		= trim($post_arr['img_caption']);
		$this->description 	= trim($post_arr['img_description']);
		$this->authorname	= preg_replace('/[^a-z ]/i', '', $post_arr['authorname']);
		$this->emailid		= $post_arr['emailid'];
		$this->status 		= $post_arr['status'];
		$this->username 	= $post_arr['username'];
		$this->password 	= $post_arr['userpassword'];

		if(!empty($this->categories)){			
			$this->__getCategoriesId($this->categories);			
		}
        
		if(!empty($this->tags)){			
			$this->__getTagsId($this->tags);			
		}
                
		if(!empty($this->authorname)){			
			$this->__getAuthorId($this->authorname);			
		}

		if(!empty($this->image_url)){			
			$this->__uploadMedia();			
		}

		$this->post_field 	= array(	'title' 			=> $this->title,
							'content' 			=> $this->content,
							'categories'		=> $this->cat_id_arr,
							'tags'				=> $this->tag_id_arr,
							'author'			=> $this->author_id,
							'featured_media'	=> $this->featured_img_id,
							'status'			=> $this->status,
                                                        'date_gmt'                      => $post_arr['date_gmt']
							  );
		//print_r(json_encode($this->post_field));
		//print_r($this->post_field);
		//exit;
	}

	public function get_file_name($path){
		$url = $path;
		$break = explode('/', $url);
		$file = $break[count($break) - 1];		
		$random_unique_no = md5(time());
		$fa = explode('.',$file);
		$extension = end($fa);
		$name_only = current(explode('.',$file));
		
		switch ($extension) {
		    case "jpg":
		    case "jpeg":
		        $file_name = $name_only . '-' . $random_unique_no . '.jpg' ;
		        break;
		    default:
       		$file_name = $file;
		}
		return $file_name; 
	}

	/*
		# Description: Upload featured media and update alt, caption and description
	*/
	private function __uploadMedia(){
		// upload featured image
		$attachment_id = '';

		$arr = array('title'       => ucwords(strtolower($this->alt_text)), 
			     'alt_text'    => ucwords(strtolower($this->alt_text)), 
			     'caption' 	   => ucwords(strtolower($this->caption)), 
			     'description' => ucfirst(strtolower($this->description)));

		if($this->image_url!=''){
			$method		= strtoupper('post');
			$file_name 	= $this->get_file_name($this->image_url);
			$file 		= file_get_contents(trim($this->image_url));
			$url 		= $this->baseurl . '/wp-json/wp/v2/media/';
			$ch 		= curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $file );		
			//curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($arr) );		
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [
				'Content-Disposition: form-data; filename="'.$file_name.'"',
				'Authorization: Basic ' . base64_encode( $this->username . ':' . $this->password ),
				"cache-control: no-cache",    		
			] );
			$result = curl_exec( $ch );
			curl_close( $ch );
			$output_attachment = json_decode( $result );			
			$attachment_id = $output_attachment->id;
                        if(empty($attachment_id)){
				$curl_url = $url . '?search='.$file_name;
				$method = strtoupper('get');
				$json = $this->__send($curl_url, $method, '', $this->username, $this->password);
                            	$arrobj= json_decode($json);
				if(!empty($arrobj)){					   
				   $attachment_id = $arrobj[0]->id; 
                                }
                        }
                        if(empty($attachment_id) && !empty($this->image_url)){
                             $feature_image = '<img src="' . $this->image_url . '" /><br>';
                             $this->content = $feature_image.$this->content;
                        }

			if($attachment_id!='' && !empty($this->alt_text)){
				$curl_url = $url . $attachment_id;
				$method = strtoupper('put');
				$json = $this->__send($curl_url, $method, $arr, $this->username, $this->password);
			}			
		}		

		return $this->featured_img_id = (int)$attachment_id;
	}
	/*
		# Description: Generates url friendly slug text
		# @ param: string
		# Output: lowercase string		
	*/
	public function slug_text($str = NULL){
		$str = str_replace('&', ' ', $str);
		$str = preg_replace('/[^A-Za-z0-9\-]/', ' ', $str);
   		$str = preg_replace('/-+/', '-', $str);   		
		//return wordwrap(strtolower(trim($str)), 1, '-', 0);
		$str = strtolower(preg_replace('/\s+/', '-',$str));
		//echo $str;
   		//exit;	
		return $str;
	}
	/*
		# Description: Generates random strong password
		# @ param: string
		# Output: hash string		
	*/
	public function random_password( $length = 8 ) {
	    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
	    $password = substr( str_shuffle( $chars ), 0, $length );
	    return md5($password);
	}
	/*
		# Description: Retrieve and create category based on WP Rest API
		# @ param: string(Optional)
		# Output: Array of integer		
	*/

	private function __getCategoriesId($catstr = NULL){
		$url = $this->baseurl . '/wp-json/wp/v2/categories/';
		$method = strtoupper('get');
		$cat_arr = explode(',',$this->categories);
			$count_cat = sizeof($cat_arr);
			
			if($count_cat > 0){
				foreach($cat_arr as $cval){
					$curl_url = $url . '?slug=' . trim($this->slug_text($cval));				
					$json = $this->__send($curl_url, $method, '', $this->username, $this->password);
					$arrobj= json_decode($json);
					//if (json_last_error() === 0) {
						if(!empty($arrobj)){					   
					   	$single_cat_id = $arrobj[0]->id; }
					   	else{

					   		$new_cat_arr = array('description'=>$cval, 'name'=>ucwords(strtolower($cval)), 'slug'=>trim($this->slug_text($cval)));

					   		$json = $this->__send($url, 'POST', $new_cat_arr, $this->username, $this->password);
					   		$arrobj= json_decode($json);
					   		//print_r($arrobj); exit;
					   		$single_cat_id = $arrobj->id;
					   	}
					//}
					if($single_cat_id != 0){
						array_push($this->cat_id_arr, $single_cat_id);
					}
					$single_cat_id 	= '';
					$curl_url 		= '';
					$json 			= '';
				}
			}			
			return $this->cat_id_arr;
	}

	private function __getTagsId($catstr = NULL){
		$url = $this->baseurl . '/wp-json/wp/v2/tags/';
		$method = strtoupper('get');
		$tag_arr = explode(',',$this->tags);
			$count_tag = sizeof($tag_arr);
			
			if($count_tag > 0){
				foreach($tag_arr as $cval){
					$curl_url = $url . '?slug=' . trim($this->slug_text($cval));					
					$json = $this->__send($curl_url, $method, '', $this->username, $this->password);
					$arrobj= json_decode($json);
					//if (json_last_error() === 0) {
						if(!empty($arrobj)){					   
					   	$single_tag_id = $arrobj[0]->id; }
					   	else{

					   		$new_tag_arr = array('description'=>$cval, 'name'=>ucwords(strtolower($cval)), 'slug'=>trim($this->slug_text($cval)));

					   		$json = $this->__send($url, 'POST', $new_tag_arr, $this->username, $this->password);
					   		$arrobj= json_decode($json);
					   		//print_r($arrobj); exit;
					   		$single_tag_id = $arrobj->id;
					   	}
					//}
					if($single_tag_id != 0){
						array_push($this->tag_id_arr, $single_tag_id);
					}
					$single_tag_id 	= '';
					$curl_url 		= '';
					$json 			= '';
				}
			}			
			return $this->tag_id_arr;
	}

	 private function __getAuthorId($authorname = NULL){
		$url 			= $this->baseurl . '/wp-json/wp/v2/users/?per_page=100';
		$method 		= strtoupper('get');
		$single_auth_id	        = '';
		$single_auth 	        = trim($this->authorname);
		//$curl_url 	        = $url . '?slug=' . trim($this->slug_text($single_auth));
		$json 			= $this->__send($url, $method, '', $this->username, $this->password);
		$arrobj			= json_decode($json);
		$user_f_l_name 	        = array();
		$ret 			= $this->recursive_array_search( $this->emailid, $arrobj );

        	if( !empty($arrobj) && !empty($ret) ){
			if( strtolower(trim($arrobj[$ret]->name)) === strtolower($single_auth) ){
				$single_auth_id = (int)$arrobj[$ret]->id ; 
			}else{
				$update_url 	= $this->baseurl . '/wp-json/wp/v2/users/'.$arrobj[$ret]->id;
				$user_f_l_name 	= explode(' ',$single_auth);					
		   		$new_author_arr = array(
		   			'description'	        => ucwords(strtolower($single_auth)),		   			
		   			'slug'			=> trim($this->slug_text($single_auth)),
					'name'			=> ucwords($single_auth),
					'nickname'		=> ucwords($single_auth),
					'first_name'	        => ucwords(current($user_f_l_name)),
					'last_name'		=> ucwords(end($user_f_l_name))					
		   		);
		   		$update_json = $this->__send($update_url, 'PUT', $new_author_arr, $this->username, $this->password);
		   		$update_arrobj= json_decode($update_json);
		   		//print_r($update_arrobj); exit;
		   		$single_auth_id = (int)$arrobj[$ret]->id ;
                     } 

		}
		else{
			if( !empty($arrobj) && $this->emailid!='' ){
				$user_f_l_name = explode(' ',$single_auth);					
			   	$new_author_arr = array(
			   		'description'	=> ucwords(strtolower($single_auth)), 
			   		'username'		=> $this->clean($single_auth), 
			   		'slug'			=> trim($this->slug_text($single_auth)),
			   		'password'		=> $this->random_password(),
					'roles'			=> 'editor',
					'name'			=> ucwords($single_auth),
					'nickname'		=> ucwords($single_auth),
					'first_name'	=> ucwords(current($user_f_l_name)),
					'last_name'		=> ucwords(end($user_f_l_name)),
					'email'			=> trim($this->emailid)
			   		);
			   	$json = $this->__send($url, 'POST', $new_author_arr, $this->username, $this->password);
			   	$arrobj= json_decode($json);
			   	//print_r($arrobj); exit;
			   	$single_auth_id = $arrobj->id;
			   }			   	
		}
		return $this->author_id = (int)$single_auth_id ;
	} 

      public function __createPost(){
		$method = strtoupper('POST');
		$url = $this->baseurl . '/wp-json/wp/v2/posts/';
		$res = $this->__send($url, $method, $this->post_field, $this->username, $this->password );
                //print_r($this->post_field); var_dump($res);exit;
		
//echo '<div class="alert alert-success">';
		//echo $res; exit;	
		//echo '</div>';
		return $res;
	}

	public function __getPost(){

		$url = $this->baseurl . '/wp-json/wp/v2/posts';
		$method = strtoupper('get');
		$curl_url = $url . '?search=' . str_replace(' ', '%20', trim($this->title)).'&status=publish,future';
		$json = $this->__send($curl_url, $method, '', $this->username, $this->password);
		$arrobj= json_decode($json, true);
		//if (json_last_error() === 0) {
		if(!empty($arrobj) && count($arrobj) > 0 ){
			foreach($arrobj as $wp){
				if(strcmp($this->title, $wp["title"]["rendered"]) == 0)
				{
					$dt = new DateTime($wp["date_gmt"]);
					$date = $dt->format('m/d/Y');
					if(strcmp(gmdate("m/d/Y"), $date)==0)
					{
					    return $wp; //"Error #: Post with given title already created today";
					}					
				}
			}
		}	   

		return false;
	}

	public function __send($url='', $method='GET', $post_fields='', $username='', $password=''){

		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_HTTPHEADER => array(		  
		  "Authorization: Basic ". base64_encode( $username . ':' . $password ),
		  "Cache-Control: no-cache",
		  "Content-Type: application/json"          
		),
		));

                if(strtolower($method) == 'post')
                {
                      curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post_fields));
                }

		$response = curl_exec($curl);
		$err = curl_error($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                
                if($httpCode == 504){
                  return "Error #: Gateway timeout";
                }
		
		if ($err) {
        	  return "Error #: " . $err;
      	        } else {	        
	          return $response;	        
      	        }

	} // end send
	/*------------------ Newly added functions on 27-2-2018 ------------------*/

	private function clean($string) {
		$string = str_replace(' ', '', $string); 
		$string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
		return strtolower(preg_replace('/-+/', '-', $string)); 
	}

	private function recursive_array_search($needle,$haystack) {
		//echo '<pre>';
		$authindex = 0;
		foreach($haystack as $key=>$value) {

			if( is_object($value) ){	
				if($value->user_email === $needle ){
                                        
					return $authindex; 
				}				
			}

		    $authindex += 1;			
		}
		return 0;
    }

 public function sanitize_permalink($string)
  {
		  $string = strtolower($string);
		  $string = $this->convert_chars($string); 
		  $string = $this->remove_accents( $string );
		  $string = $this->sanitize_title($string);  
		  $string = str_replace('&', '', trim($string));  
		  $string = str_replace('038', '', trim($string));  
		  $string = trim($string);
		  $string = str_replace(' ', '-', trim($string)); 
		  $string = preg_replace("/\<[^)]+\>/","",trim($string));
		  $string = preg_replace('/[^A-Za-z0-9\-]/', '', trim($string));
		  $string = preg_replace('/-+/', '-', trim($string));
		  $string = filter_var($string, FILTER_SANITIZE_STRING);
		  $string = trim($string, "-");

		  return $string;  
  }
  
	private function manage_slash( $value ) {
	  if ( is_array( $value ) ) {
	    foreach ( $value as $k => $v ) {
	      if ( is_array( $v ) ) {
	        $value[$k] = $this->manage_slash( $v );
	      } else {
	        $value[$k] = addslashes( $v );
	      }
	    }
	  } else {
	    $value = addslashes( $value );
	  }

	  return $value;
	}

	//  1. Function
	private function convert_chars($string =''){

	  $content = '';
	  $string = rtrim( $string, '/\\' );
	  $content = $this->manage_slash( $string );

	  if ( strpos( $content, '&' ) !== false ) {
	    $content = preg_replace( '/&([^#])(?![a-z1-4]{1,8};)/i', '&#038;$1', $content );
	  }


	  $content = $this->convert_invalid_entities($content);

	  return $content;
	}


	private function convert_invalid_entities( $content ) {
	  $custom_htmltranswinuni = array(
	    '&#128;' => '&#8364;', // the Euro sign
	    '&#129;' => '',
	    '&#130;' => '&#8218;', // these are Windows CP1252 specific characters
	    '&#131;' => '&#402;',  // they would look weird on non-Windows browsers
	    '&#132;' => '&#8222;',
	    '&#133;' => '&#8230;',
	    '&#134;' => '&#8224;',
	    '&#135;' => '&#8225;',
	    '&#136;' => '&#710;',
	    '&#137;' => '&#8240;',
	    '&#138;' => '&#352;',
	    '&#139;' => '&#8249;',
	    '&#140;' => '&#338;',
	    '&#141;' => '',
	    '&#142;' => '&#381;',
	    '&#143;' => '',
	    '&#144;' => '',
	    '&#145;' => '&#8216;',
	    '&#146;' => '&#8217;',
	    '&#147;' => '&#8220;',
	    '&#148;' => '&#8221;',
	    '&#149;' => '&#8226;',
	    '&#150;' => '&#8211;',
	    '&#151;' => '&#8212;',
	    '&#152;' => '&#732;',
	    '&#153;' => '&#8482;',
	    '&#154;' => '&#353;',
	    '&#155;' => '&#8250;',
	    '&#156;' => '&#339;',
	    '&#157;' => '',
	    '&#158;' => '&#382;',
	    '&#159;' => '&#376;'
	  );

	  if ( strpos( $content, '&#1' ) !== false ) {
	    $content = strtr( $content, $custom_htmltranswinuni );
	  }

	  return $content;
	}

	private function seems_utf8( $str ) {
	  //mbstring_binary_safe_encoding();
	  $length = strlen($str);
	  //reset_mbstring_encoding();
	  for ($i=0; $i < $length; $i++) {
	    $c = ord($str[$i]);
	    if ($c < 0x80) $n = 0; // 0bbbbbbb
	    elseif (($c & 0xE0) == 0xC0) $n=1; // 110bbbbb
	    elseif (($c & 0xF0) == 0xE0) $n=2; // 1110bbbb
	    elseif (($c & 0xF8) == 0xF0) $n=3; // 11110bbb
	    elseif (($c & 0xFC) == 0xF8) $n=4; // 111110bb
	    elseif (($c & 0xFE) == 0xFC) $n=5; // 1111110b
	    else return false; // Does not match any model
	    for ($j=0; $j<$n; $j++) { // n bytes matching 10bbbbbb follow ?
	      if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
	        return false;
	    }
	  }
	  return true;
	}

	// 2. Function
	private function remove_accents( $str, $utf8=true )
	{
	    $str = (string)$str;
	    if( is_null($utf8) ) {

	        if( !function_exists('mb_detect_encoding') ) {
	            $utf8 = (strtolower( mb_detect_encoding($str) )=='utf-8');
	        } else {
	            $length = strlen($str);
	            $utf8 = true;
	            for ($i=0; $i < $length; $i++) {
	                $c = ord($str[$i]);
	                if ($c < 0x80) $n = 0; # 0bbbbbbb
	                elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
	                elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
	                elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
	                elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
	                elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
	                else return false; # Does not match any model
	                for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
	                    if ((++$i == $length)
	                        || ((ord($str[$i]) & 0xC0) != 0x80)) {
	                        $utf8 = false;
	                        break;
	                    }
	                    
	                }
	            }
	        }
	        
	    }
	    
	    if(!$utf8)
	        $str = utf8_encode($str);

	    $transliteration = array(
	    '/Ĳ/' => 'I', '/Ö/' => 'O','Œ' => 'O','Ü' => 'U','ä' => 'a','æ' => 'a',
	    'ĳ' => 'i','ö' => 'o','œ' => 'o','ü' => 'u','ß' => 's','ſ' => 's',
	    'À' => 'A','Á' => 'A','Â' => 'A','Ã' => 'A','Ä' => 'A','Å' => 'A',
	    'Æ' => 'A','Ā' => 'A','Ą' => 'A','Ă' => 'A','Ç' => 'C','Ć' => 'C',
	    'Č' => 'C','Ĉ' => 'C','Ċ' => 'C','Ď' => 'D','Đ' => 'D','È' => 'E',
	    'É' => 'E','Ê' => 'E','Ë' => 'E','Ē' => 'E','Ę' => 'E','Ě' => 'E',
	    'Ĕ' => 'E','Ė' => 'E','Ĝ' => 'G','Ğ' => 'G','Ġ' => 'G','Ģ' => 'G',
	    'Ĥ' => 'H','Ħ' => 'H','Ì' => 'I','Í' => 'I','Î' => 'I','Ï' => 'I',
	    'Ī' => 'I','Ĩ' => 'I','Ĭ' => 'I','Į' => 'I','İ' => 'I','Ĵ' => 'J',
	    'Ķ' => 'K','Ľ' => 'K','Ĺ' => 'K','Ļ' => 'K','Ŀ' => 'K','Ł' => 'L',
	    'Ñ' => 'N','Ń' => 'N','Ň' => 'N','Ņ' => 'N','Ŋ' => 'N','Ò' => 'O',
	    'Ó' => 'O','Ô' => 'O','Õ' => 'O','Ø' => 'O','Ō' => 'O','Ő' => 'O',
	    'Ŏ' => 'O','Ŕ' => 'R','Ř' => 'R','Ŗ' => 'R','Ś' => 'S','Ş' => 'S',
	    'Ŝ' => 'S','Ș' => 'S','Š' => 'S','Ť' => 'T','Ţ' => 'T','Ŧ' => 'T',
	    'Ț' => 'T','Ù' => 'U','Ú' => 'U','Û' => 'U','Ū' => 'U','Ů' => 'U',
	    'Ű' => 'U','Ŭ' => 'U','Ũ' => 'U','Ų' => 'U','Ŵ' => 'W','Ŷ' => 'Y',
	    'Ÿ' => 'Y','Ý' => 'Y','Ź' => 'Z','Ż' => 'Z','Ž' => 'Z','à' => 'a',
	    'á' => 'a','â' => 'a','ã' => 'a','ā' => 'a','ą' => 'a','ă' => 'a',
	    'å' => 'a','ç' => 'c','ć' => 'c','č' => 'c','ĉ' => 'c','ċ' => 'c',
	    'ď' => 'd','đ' => 'd','è' => 'e','é' => 'e','ê' => 'e','ë' => 'e',
	    'ē' => 'e','ę' => 'e','ě' => 'e','ĕ' => 'e','ė' => 'e','ƒ' => 'f',
	    'ĝ' => 'g','ğ' => 'g','ġ' => 'g','ģ' => 'g','ĥ' => 'h','ħ' => 'h',
	    'ì' => 'i','í' => 'i','î' => 'i','ï' => 'i','ī' => 'i','ĩ' => 'i',
	    'ĭ' => 'i','į' => 'i','ı' => 'i','ĵ' => 'j','ķ' => 'k','ĸ' => 'k',
	    'ł' => 'l','ľ' => 'l','ĺ' => 'l','ļ' => 'l','ŀ' => 'l','ñ' => 'n',
	    'ń' => 'n','ň' => 'n','ņ' => 'n','ŉ' => 'n','ŋ' => 'n','ò' => 'o',
	    'ó' => 'o','ô' => 'o','õ' => 'o','ø' => 'o','ō' => 'o','ő' => 'o',
	    'ŏ' => 'o','ŕ' => 'r','ř' => 'r','ŗ' => 'r','ś' => 's','š' => 's',
	    'ť' => 't','ù' => 'u','ú' => 'u','û' => 'u','ū' => 'u','ů' => 'u',
	    'ű' => 'u','ŭ' => 'u','ũ' => 'u','ų' => 'u','ŵ' => 'w','ÿ' => 'y',
	    'ý' => 'y','ŷ' => 'y','ż' => 'z','ź' => 'z','ž' => 'z','Α' => 'A',
	    'Ά' => 'A','Ἀ' => 'A','Ἁ' => 'A','Ἂ' => 'A','Ἃ' => 'A','Ἄ' => 'A',
	    'Ἅ' => 'A','Ἆ' => 'A','Ἇ' => 'A','ᾈ' => 'A','ᾉ' => 'A','ᾊ' => 'A',
	    'ᾋ' => 'A','ᾌ' => 'A','ᾍ' => 'A','ᾎ' => 'A','ᾏ' => 'A','Ᾰ' => 'A',
	    'Ᾱ' => 'A','Ὰ' => 'A','ᾼ' => 'A','Β' => 'B','Γ' => 'G','Δ' => 'D',
	    'Ε' => 'E','Έ' => 'E','Ἐ' => 'E','Ἑ' => 'E','Ἒ' => 'E','Ἓ' => 'E',
	    'Ἔ' => 'E','Ἕ' => 'E','Ὲ' => 'E','Ζ' => 'Z','Η' => 'I','Ή' => 'I',
	    'Ἠ' => 'I','Ἡ' => 'I','Ἢ' => 'I','Ἣ' => 'I','Ἤ' => 'I','Ἥ' => 'I',
	    'Ἦ' => 'I','Ἧ' => 'I','ᾘ' => 'I','ᾙ' => 'I','ᾚ' => 'I','ᾛ' => 'I',
	    'ᾜ' => 'I','ᾝ' => 'I','ᾞ' => 'I','ᾟ' => 'I','Ὴ' => 'I','ῌ' => 'I',
	    'Θ' => 'T','Ι' => 'I','Ί' => 'I','Ϊ' => 'I','Ἰ' => 'I','Ἱ' => 'I',
	    'Ἲ' => 'I','Ἳ' => 'I','Ἴ' => 'I','Ἵ' => 'I','Ἶ' => 'I','Ἷ' => 'I',
	    'Ῐ' => 'I','Ῑ' => 'I','Ὶ' => 'I','Κ' => 'K','Λ' => 'L','Μ' => 'M',
	    'Ν' => 'N','Ξ' => 'K','Ο' => 'O','Ό' => 'O','Ὀ' => 'O','Ὁ' => 'O',
	    'Ὂ' => 'O','Ὃ' => 'O','Ὄ' => 'O','Ὅ' => 'O','Ὸ' => 'O','Π' => 'P',
	    'Ρ' => 'R','Ῥ' => 'R','Σ' => 'S','Τ' => 'T','Υ' => 'Y','Ύ' => 'Y',
	    'Ϋ' => 'Y','Ὑ' => 'Y','Ὓ' => 'Y','Ὕ' => 'Y','Ὗ' => 'Y','Ῠ' => 'Y',
	    'Ῡ' => 'Y','Ὺ' => 'Y','Φ' => 'F','Χ' => 'X','Ψ' => 'P','Ω' => 'O',
	    'Ώ' => 'O','Ὠ' => 'O','Ὡ' => 'O','Ὢ' => 'O','Ὣ' => 'O','Ὤ' => 'O',
	    'Ὥ' => 'O','Ὦ' => 'O','Ὧ' => 'O','ᾨ' => 'O','ᾩ' => 'O','ᾪ' => 'O',
	    'ᾫ' => 'O','ᾬ' => 'O','ᾭ' => 'O','ᾮ' => 'O','ᾯ' => 'O','Ὼ' => 'O',
	    'ῼ' => 'O','α' => 'a','ά' => 'a','ἀ' => 'a','ἁ' => 'a','ἂ' => 'a',
	    'ἃ' => 'a','ἄ' => 'a','ἅ' => 'a','ἆ' => 'a','ἇ' => 'a','ᾀ' => 'a',
	    'ᾁ' => 'a','ᾂ' => 'a','ᾃ' => 'a','ᾄ' => 'a','ᾅ' => 'a','ᾆ' => 'a',
	    'ᾇ' => 'a','ὰ' => 'a','ᾰ' => 'a','ᾱ' => 'a','ᾲ' => 'a','ᾳ' => 'a',
	    'ᾴ' => 'a','ᾶ' => 'a','ᾷ' => 'a','β' => 'b','γ' => 'g','δ' => 'd',
	    'ε' => 'e','έ' => 'e','ἐ' => 'e','ἑ' => 'e','ἒ' => 'e','ἓ' => 'e',
	    'ἔ' => 'e','ἕ' => 'e','ὲ' => 'e','ζ' => 'z','η' => 'i','ή' => 'i',
	    'ἠ' => 'i','ἡ' => 'i','ἢ' => 'i','ἣ' => 'i','ἤ' => 'i','ἥ' => 'i',
	    'ἦ' => 'i','ἧ' => 'i','ᾐ' => 'i','ᾑ' => 'i','ᾒ' => 'i','ᾓ' => 'i',
	    'ᾔ' => 'i','ᾕ' => 'i','ᾖ' => 'i','ᾗ' => 'i','ὴ' => 'i','ῂ' => 'i',
	    'ῃ' => 'i','ῄ' => 'i','ῆ' => 'i','ῇ' => 'i','θ' => 't','ι' => 'i',
	    'ί' => 'i','ϊ' => 'i','ΐ' => 'i','ἰ' => 'i','ἱ' => 'i','ἲ' => 'i',
	    'ἳ' => 'i','ἴ' => 'i','ἵ' => 'i','ἶ' => 'i','ἷ' => 'i','ὶ' => 'i',
	    'ῐ' => 'i','ῑ' => 'i','ῒ' => 'i','ῖ' => 'i','ῗ' => 'i','κ' => 'k',
	    'λ' => 'l','μ' => 'm','ν' => 'n','ξ' => 'k','ο' => 'o','ό' => 'o',
	    'ὀ' => 'o','ὁ' => 'o','ὂ' => 'o','ὃ' => 'o','ὄ' => 'o','ὅ' => 'o',
	    'ὸ' => 'o','π' => 'p','ρ' => 'r','ῤ' => 'r','ῥ' => 'r','σ' => 's',
	    'ς' => 's','τ' => 't','υ' => 'y','ύ' => 'y','ϋ' => 'y','ΰ' => 'y',
	    'ὐ' => 'y','ὑ' => 'y','ὒ' => 'y','ὓ' => 'y','ὔ' => 'y','ὕ' => 'y',
	    'ὖ' => 'y','ὗ' => 'y','ὺ' => 'y','ῠ' => 'y','ῡ' => 'y','ῢ' => 'y',
	    'ῦ' => 'y','ῧ' => 'y','φ' => 'f','χ' => 'x','ψ' => 'p','ω' => 'o',
	    'ώ' => 'o','ὠ' => 'o','ὡ' => 'o','ὢ' => 'o','ὣ' => 'o','ὤ' => 'o',
	    'ὥ' => 'o','ὦ' => 'o','ὧ' => 'o','ᾠ' => 'o','ᾡ' => 'o','ᾢ' => 'o',
	    'ᾣ' => 'o','ᾤ' => 'o','ᾥ' => 'o','ᾦ' => 'o','ᾧ' => 'o','ὼ' => 'o',
	    'ῲ' => 'o','ῳ' => 'o','ῴ' => 'o','ῶ' => 'o','ῷ' => 'o','А' => 'A',
	    'Б' => 'B','В' => 'V','Г' => 'G','Д' => 'D','Е' => 'E','Ё' => 'E',
	    'Ж' => 'Z','З' => 'Z','И' => 'I','Й' => 'I','К' => 'K','Л' => 'L',
	    'М' => 'M','Н' => 'N','О' => 'O','П' => 'P','Р' => 'R','С' => 'S',
	    'Т' => 'T','У' => 'U','Ф' => 'F','Х' => 'K','Ц' => 'T','Ч' => 'C',
	    'Ш' => 'S','Щ' => 'S','Ы' => 'Y','Э' => 'E','Ю' => 'Y','Я' => 'Y',
	    'а' => 'A','б' => 'B','в' => 'V','г' => 'G','д' => 'D','е' => 'E',
	    'ё' => 'E','ж' => 'Z','з' => 'Z','и' => 'I','й' => 'I','к' => 'K',
	    'л' => 'L','м' => 'M','н' => 'N','о' => 'O','п' => 'P','р' => 'R',
	    'с' => 'S','т' => 'T','у' => 'U','ф' => 'F','х' => 'K','ц' => 'T',
	    'ч' => 'C','ш' => 'S','щ' => 'S','ы' => 'Y','э' => 'E','ю' => 'Y',
	    'я' => 'Y','ð' => 'd','Ð' => 'D','þ' => 't','Þ' => 'T','ა' => 'a',
	    'ბ' => 'b','გ' => 'g','დ' => 'd','ე' => 'e','ვ' => 'v','ზ' => 'z',
	    'თ' => 't','ი' => 'i','კ' => 'k','ლ' => 'l','მ' => 'm','ნ' => 'n',
	    'ო' => 'o','პ' => 'p','ჟ' => 'z','რ' => 'r','ს' => 's','ტ' => 't',
	    'უ' => 'u','ფ' => 'p','ქ' => 'k','ღ' => 'g','ყ' => 'q','შ' => 's',
	    'ჩ' => 'c','ც' => 't','ძ' => 'd','წ' => 't','ჭ' => 'c','ხ' => 'k',
	    'ჯ' => 'j','ჰ' => 'h'
	    );
	    $str = str_replace( array_keys( $transliteration ),
	                        array_values( $transliteration ),
	                        $str);

	    return $str;
	}

	// 3. sanitize title
	private function sanitize_title( $title ) {
	  $raw_title = $title;  
	  $title = $this->remove_accents($title);
	  return $title;
	}

} // end class
