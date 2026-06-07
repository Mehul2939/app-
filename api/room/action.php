<?php
require_once __DIR__ . '/../helpers/rooms.php';$me=current_user();$d=input();$id=(int)($d['room_id']??0);$action=clean_string($d['action']??'',40);$target=(int)($d['user_id']??0);$role=room_role($id,(int)$me['id']);$mods=['owner','coadmin'];
if($action==='leave'){db()->prepare('UPDATE room_participants SET left_at=NOW() WHERE room_id=? AND user_id=?')->execute([$id,$me['id']]);db()->prepare('UPDATE room_seats SET user_id=NULL,mic_muted=1 WHERE room_id=? AND user_id=?')->execute([$id,$me['id']]);refresh_room_count($id);room_system_message($id,($me['name']?:$me['username']).' left the room');json_response(['success'=>true]);}
if($action==='follow'){ $room=room_by_id($id);db()->prepare('INSERT IGNORE INTO room_follows(room_id,follower_id,host_id) VALUES(?,?,?)')->execute([$id,$me['id'],$room['owner_id']]);json_response(['success'=>true]);}
if($action==='raise_hand'){db()->prepare('UPDATE room_participants SET raised_hand=1 WHERE room_id=? AND user_id=? AND left_at IS NULL')->execute([$id,$me['id']]);room_system_message($id,($me['name']?:$me['username']).' raised hand');json_response(['success'=>true]);}
if($action==='request_seat'){db()->prepare("INSERT INTO room_seat_requests(room_id,user_id,seat_number,status) VALUES(?,?,?,'pending') ON DUPLICATE KEY UPDATE seat_number=VALUES(seat_number),status='pending'")->execute([$id,$me['id'],(int)($d['seat_number']??0)?:null]);json_response(['success'=>true]);}
if($action==='take_seat'){
    $seat=(int)($d['seat_number']??0);
    if($seat<1||$seat>8)json_response(['success'=>false,'message'=>'Invalid seat'],422);
    $pdo=db();
    try{
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT is_locked,user_id FROM room_seats WHERE room_id=? AND seat_number=? FOR UPDATE');
        $stmt->execute([$id,$seat]);
        $row=$stmt->fetch();
        if(!$row){throw new RuntimeException('Seat not found');}
        if((int)$row['is_locked']===1){throw new RuntimeException('Seat is locked');}
        if(!empty($row['user_id'])){throw new RuntimeException('Seat already taken');}
        $pdo->prepare('UPDATE room_seats SET user_id=NULL,mic_muted=1 WHERE room_id=? AND user_id=?')->execute([$id,$me['id']]);
        $pdo->prepare('UPDATE room_seats SET user_id=?,mic_muted=0 WHERE room_id=? AND seat_number=?')->execute([$me['id'],$id,$seat]);
        $pdo->prepare("UPDATE room_participants SET role=IF(role IN('owner','coadmin'),role,'speaker'),raised_hand=0,mic_muted=0,left_at=NULL,last_seen_at=NOW() WHERE room_id=? AND user_id=?")->execute([$id,$me['id']]);
        $pdo->prepare("UPDATE room_seat_requests SET status='accepted' WHERE room_id=? AND user_id=? AND status='pending'")->execute([$id,$me['id']]);
        $pdo->commit();
        room_system_message($id,($me['name']?:$me['username']).' took a speaker seat');
        json_response(['success'=>true,'role'=>'speaker']);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        json_response(['success'=>false,'message'=>$e->getMessage()],409);
    }
}
if($action==='mic' && $target===(int)$me['id']){
    $isSpeaker=db()->prepare("SELECT role FROM room_participants WHERE room_id=? AND user_id=? AND left_at IS NULL AND role IN('owner','coadmin','speaker') LIMIT 1");
    $isSpeaker->execute([$id,$me['id']]);
    if(!$isSpeaker->fetch())json_response(['success'=>false,'message'=>'Only speakers can control mic'],403);
    db()->prepare('UPDATE room_participants SET mic_muted=? WHERE room_id=? AND user_id=?')->execute([!empty($d['muted'])?1:0,$id,$me['id']]);
    db()->prepare('UPDATE room_seats SET mic_muted=? WHERE room_id=? AND user_id=?')->execute([!empty($d['muted'])?1:0,$id,$me['id']]);
    json_response(['success'=>true]);
}
require_room_role($id,(int)$me['id'],$mods);
if($action==='seat_lock'){db()->prepare('UPDATE room_seats SET is_locked=? WHERE room_id=? AND seat_number=?')->execute([!empty($d['locked'])?1:0,$id,(int)$d['seat_number']]);}
elseif($action==='seat_accept'){ $seat=(int)($d['seat_number']??0);db()->prepare('UPDATE room_seats SET user_id=?,mic_muted=0 WHERE room_id=? AND seat_number=? AND is_locked=0 AND user_id IS NULL')->execute([$target,$id,$seat]);db()->prepare("UPDATE room_participants SET role='speaker',raised_hand=0,mic_muted=0 WHERE room_id=? AND user_id=?")->execute([$id,$target]);db()->prepare("UPDATE room_seat_requests SET status='accepted' WHERE room_id=? AND user_id=? AND status='pending'")->execute([$id,$target]);room_system_message($id,'A user took a speaker seat');}
elseif($action==='seat_reject'){db()->prepare("UPDATE room_seat_requests SET status='rejected' WHERE room_id=? AND user_id=? AND status='pending'")->execute([$id,$target]);}
elseif($action==='remove_seat'){db()->prepare('UPDATE room_seats SET user_id=NULL,mic_muted=1 WHERE room_id=? AND user_id=?')->execute([$id,$target]);db()->prepare("UPDATE room_participants SET role='listener',mic_muted=1 WHERE room_id=? AND user_id=?")->execute([$id,$target]);}
elseif($action==='mic'){db()->prepare('UPDATE room_participants SET mic_muted=? WHERE room_id=? AND user_id=?')->execute([!empty($d['muted'])?1:0,$id,$target]);db()->prepare('UPDATE room_seats SET mic_muted=? WHERE room_id=? AND user_id=?')->execute([!empty($d['muted'])?1:0,$id,$target]);}
elseif($action==='kick'){if($target===(int)room_by_id($id)['owner_id'])json_response(['success'=>false,'message'=>'Owner cannot be removed'],403);db()->prepare('INSERT INTO room_kicks(room_id,user_id,kicked_by,reason) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE kicked_by=VALUES(kicked_by),reason=VALUES(reason),created_at=NOW()')->execute([$id,$target,$me['id'],clean_string($d['reason']??'',255)]);db()->prepare('UPDATE room_participants SET left_at=NOW() WHERE room_id=? AND user_id=?')->execute([$id,$target]);}
elseif($action==='lock'){db()->prepare('UPDATE rooms SET is_locked=?,password_hash=IF(?="",password_hash,?) WHERE id=?')->execute([!empty($d['locked'])?1:0,clean_string($d['password']??'',100),!empty($d['password'])?password_hash((string)$d['password'],PASSWORD_DEFAULT):null,$id]);room_system_message($id,!empty($d['locked'])?'Room locked':'Room unlocked');}
elseif($action==='announcement'){db()->prepare('UPDATE rooms SET announcement=? WHERE id=?')->execute([clean_string($d['announcement']??'',255),$id]);}
elseif($action==='close'){require_room_role($id,(int)$me['id'],['owner']);db()->prepare("UPDATE rooms SET status='closed',active_users=0 WHERE id=?")->execute([$id]);}
elseif($action==='admin_add'){require_room_role($id,(int)$me['id'],['owner']);if((int)db()->query("SELECT COUNT(*) FROM room_admins WHERE room_id=$id")->fetchColumn()>=5)json_response(['success'=>false,'message'=>'Maximum 5 co-admins allowed'],409);db()->prepare('INSERT IGNORE INTO room_admins(room_id,user_id,added_by) VALUES(?,?,?)')->execute([$id,$target,$me['id']]);db()->prepare("UPDATE room_participants SET role='coadmin' WHERE room_id=? AND user_id=?")->execute([$id,$target]);}
elseif($action==='admin_remove'){require_room_role($id,(int)$me['id'],['owner']);db()->prepare('DELETE FROM room_admins WHERE room_id=? AND user_id=?')->execute([$id,$target]);db()->prepare("UPDATE room_participants SET role='listener' WHERE room_id=? AND user_id=?")->execute([$id,$target]);}
else json_response(['success'=>false,'message'=>'Invalid room action'],422);refresh_room_count($id);json_response(['success'=>true]);
