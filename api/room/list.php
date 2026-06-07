<?php
require_once __DIR__ . '/../helpers/auth.php';
$me=current_user(); $q=clean_string($_GET['q']??'',100); $category=clean_string($_GET['category']??'',20); $type=clean_string($_GET['type']??'',20);
$where=["r.status='active'","r.visibility='public'","NOT EXISTS(SELECT 1 FROM blocked_users b WHERE (b.blocker_id=? AND b.blocked_id=r.owner_id) OR (b.blocker_id=r.owner_id AND b.blocked_id=?))"];
$params=[$me['id'],$me['id']];
if($q!==''){ $where[]='(r.title LIKE ? OR u.name LIKE ?)'; $params[]="%$q%";$params[]="%$q%"; }
if(in_array($category,['music','dating','study','gaming','fun','help'],true)){ $where[]='r.category=?';$params[]=$category; }
if(in_array($type,['voice','video'],true)){ $where[]='r.room_type=?';$params[]=$type; }
$stmt=db()->prepare("SELECT r.*,u.name owner_name,u.username owner_username,COALESCE(u.profile_photo,p.profile_photo) owner_photo,
 EXISTS(SELECT 1 FROM room_follows f WHERE f.follower_id=? AND f.host_id=r.owner_id) following_host
 FROM rooms r JOIN users u ON u.id=r.owner_id LEFT JOIN user_profiles p ON p.user_id=u.id WHERE ".implode(' AND ',$where)." ORDER BY r.active_users DESC,r.id DESC LIMIT 100");
$stmt->execute(array_merge([$me['id']],$params)); json_response(['success'=>true,'rooms'=>$stmt->fetchAll()]);
