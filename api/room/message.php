<?php
require_once __DIR__ . '/../helpers/rooms.php';$me=current_user();$d=input();$id=(int)($d['room_id']??0);require_room_role($id,(int)$me['id'],['owner','coadmin','speaker','listener']);
$text=clean_string($d['message_text']??'',1000);$type=clean_string($d['message_type']??'text',20);if($text===''||!in_array($type,['text','emoji','gif'],true))json_response(['success'=>false,'message'=>'Message required'],422);
db()->prepare('INSERT INTO room_messages(room_id,user_id,message_type,message_text) VALUES(?,?,?,?)')->execute([$id,$me['id'],$type,$text]);json_response(['success'=>true,'message_id'=>(int)db()->lastInsertId()]);
