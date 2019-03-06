<?php 
 
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

function  cron_post_bd9eb090() {
    // do stuff
  //массив рубрик в ВП
$rub_arr =  array (
	'рубрика1' => 1,
	'рубрика2' => 2,
	'рубрика3' => 3,
	'рубрика4' => 4,
	'рубрика5' => 5);
// ID нашего сообщества или страницы вконтакте
$wall_id="-xxxxxxxxxxx"; //  ид   группы
 
// Удаляем минус у ID групп, что мы используем выше (понадобится для ссылки).
$group_id = preg_replace("/-/i", "", $wall_id);

// Количество записей, которое нам нужно получить.
$count = "10";
 
// Токен
$token = "xxxxxxxxxx";
 
// Получаем информацию, подставив все данные выше.
$api = file_get_contents("https://api.vk.com/api.php?oauth=1&method=wall.get&owner_id={$wall_id}&count={$count}&v=5.58&access_token={$token}");
 
// Преобразуем JSON-строку в массив
$wall = json_decode($api);
//var_dump($wall);
 
// Получаем массив
$wall = $wall->response->items;
 
//получим дату последне	 записи, чтобы старые посты не загружать
$args = array(
	'numberposts' => 1);
$recent_posts = get_posts($args);
$last_post = $recent_posts[0];
$last_date = $last_post->post_date;

// Обрабатываем данные массива с помощью for и выводим нужные значения
for ($i = 0; $i < count($wall); $i++) {
	if (strlen($wall[$i]->text)<50 or $wall[$i]->marked_as_ads==1 or $wall[$i]->is_pinned==1 or date("Y-m-d H:i:s", $wall[$i]->date)<= $last_date   or !($wall[$i]->attachments) ) { continue;}
	$imgSrc = [];
	$vidSrc	= [];
	$icon='';
	// $nn = 0; //будем записывать количество фоток в посте, если не одной, то не будем размещать пост
	foreach ($wall[$i]->attachments as $key => $value) {
		if ($value->photo) {
			if (!$icon){
				$icon  = ($value->photo->photo_604)?$value->photo->photo_604:$value->photo->photo_320;
			}
			$imgSrc[] =  ($value->photo->photo_604)?$value->photo->photo_604:$value->photo->photo_320;
    }
    if ($value->video) {
    	if ($value->video->platform == "YouTube") {
    		$ps = strpos($value->video->description, 'сточник:');
    		if (!$ps) {
    			$vidSrc[] = '-';
    		}
    		else {
    			$vidSrc[]	=  substr($value->video->description, $ps+16);
    		}
    		$icon_vid = ($value->video->photo_640)?$value->video->photo_640:$value->video->photo_800;
    	}
    	else {
    		$fp = strpos($value->video->description, 'iframe src'); //получим позицуию тег iframe 
    		if ($fp>0){ 
    			$cod_iframe = substr($value->video->description,$fp-1);
    			$sp = strpos($cod_iframe,'//'); //получим позицию начала ссылки
    			$prp = strpos($cod_iframe,'" '); //получим позицию конца ссылки
    			$len_src = $prp - $sp + 1;  //получим длину ссылки
    			$podstr_before = substr($cod_iframe,0,$sp); //получим код перед подстрокой т.е. <iframe src="
    			$podstr_after = substr($cod_iframe,$prp+1);  //получим код после подстроки т.е.    width="..." height="..." frameborder="0" allowfullscreen></iframe>
    			$podstr = substr($cod_iframe,$sp,$len_src); //получим саму подстроку
    			$podstr= str_replace(' ', '', $podstr);
    			$podstr_end = 'width="640" height="360" frameborder="0" allowfullscreen></iframe>';
    			$vidSrc[] = '[/embed]'.$podstr_before.$podstr.$podstr_end.'[embed]'; 
    			}
    		else {
    			$vidSrc[] = 'ссылка на видео vk.com/video'.$value->video->owner_id.'_'.$value->video->id;
    		}
    		//если не вставлять видео, а только ссылки на видео в ВК то использовать код ниже, иначе код выше, а ниже закомментить
    		$vidSrc[] = 'vk.com/video'.$value->video->owner_id.'_'.$value->video->id;
    		if (!$icon){
				$icon_vid  = ($value->video->photo_800)?$value->video->photo_800:$value->video->photo_320;
			}
    	}
    }   
	}
	if (!$icon){
		$icon = $icon_vid;
	}
	$rp = strpos($wall[$i]->text, '#'); //позиция # чтобы найти после нее имя рубрики
	$rubric = substr($wall[$i]->text, $rp);
	$pp = strpos($rubric, ' ');   //найдем конец рубрики, чтобы последующие теги не вошли в рубрику (рубрика это самый первый тег)
	if ($pp) {
		$rubric = substr($rubric,1, $pp);	
	}
	else {
		$rubric = substr($rubric,1);
	}

	$numb_rubr = $rub_arr[$rubric];  //номер рубрики

	$tp = strpos($wall[$i]->text, '.'); //позиция точки, чтобы получить первое предложение поста = титл
	$tv = strpos($wall[$i]->text, '!'); //позиция воскл. знака, чтобы получить первое предложение поста = титл
	$tvp = strpos($wall[$i]->text, '?');//позиция вопр.знака, чтобы получить первое предложение поста = титл
 
	($tp==0)?$tp=10000:$tp; //если позиция равна 0, т.е. нет знака такого, то присвоим позиции число 10000, чтобы затем проще было выбрать минимальное
	($tv==0)?$tv=10000:$tv;
	($tvp==0)?$tvp=10000:$tvp;
	$tt = array($tp,$tv,$tvp); //составим массив из позиций знаков препинания чтобы найти минимальный, т.е. первый из них.
	$tp = min($tt);

	$title = substr($wall[$i]->text, 0, $tp+1);

	$text =  substr($wall[$i]->text, $tp+2)."<br><br>";
 

if($imgSrc){foreach ($imgSrc as $key => $value) {
	if ($value <> $icon)
	   $text = $text."<img src=".$value."  class='alignnone size-medium'/><br>";
}
}
if($vidSrc){foreach ($vidSrc as $key => $value) {
	 $text= "Ссылка на видео в ВК: ".$text."[embed]".$value."[/embed]<br>";
}}
// Получим путь до директории загрузок.
$wp_upload_dir = wp_upload_dir();
//массив с данными для вставки записи
$my_postarr = array(
	'post_title' => $title,
	'post_content'  => $text,
	'post_status'   => 'publish',
	 'post_type' => 'post',    
	 'post_category' => array($numb_rubr),
	 'post_date' => date("Y-m-d H:i:s", $wall[$i]->date) );
//id новой записи
$my_post_id = wp_insert_post($my_postarr);
// загрузим изображение иконку
$img_tag = media_sideload_image( $icon, $my_post_id,$desc = null,$return = 'src');

// Проверим тип поста, который мы будем использовать в поле 'post_mime_type'.
$filetype = wp_check_filetype( basename( $img_tag ), null );
// Подготовим массив с необходимыми данными для вложения.
$attachment = array(
	'guid'           => $wp_upload_dir['url'] . '/' . basename( $img_tag ), 
	'post_mime_type' => $filetype['type'],
	'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $img_tag ) ),
	'post_content'   => '',
	'post_status'    => 'inherit'
);
// Вставляем запись в базу данных.
$attach_id = wp_insert_attachment( $attachment, $img_tag, $my_post_id );
// Подключим нужный файл, если он еще не подключен
// wp_generate_attachment_metadata() зависит от этого файла.
require_once( ABSPATH . 'wp-admin/includes/image.php' );

// Создадим метаданные для вложения и обновим запись в базе данных.
$attach_data = wp_generate_attachment_metadata( $attach_id, $img_tag );
wp_update_attachment_metadata( $attach_id, $attach_data );
//добавим миниатюру
add_post_meta($my_post_id, '_thumbnail_id', $attach_id, true);
}
}
add_action( 'post', 'cron_post_bd9eb090', 10, 0 );
?>
