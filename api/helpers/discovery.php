<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/social.php';

function discovery_select_sql(): string
{
    return "u.id,u.public_user_id,u.name,u.username,u.gender,u.state,u.city,u.is_demo_user,u.bio,u.interests,u.profile_photo,
      u.last_active_at,u.is_online,u.created_at,u.preferred_gender_filter,
      TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE()) age,
      CASE WHEN me.latitude IS NOT NULL AND me.longitude IS NOT NULL AND u.latitude IS NOT NULL AND u.longitude IS NOT NULL
        THEN ROUND(6371 * ACOS(LEAST(1, COS(RADIANS(me.latitude))*COS(RADIANS(u.latitude))*COS(RADIANS(u.longitude)-RADIANS(me.longitude))+SIN(RADIANS(me.latitude))*SIN(RADIANS(u.latitude)))),1)
        ELSE NULL END distance_km,
      CASE WHEN u.city IS NOT NULL AND u.city<>'' AND LOWER(u.city)=LOWER(me.city) THEN 1 ELSE 0 END same_city,
      CASE WHEN u.state IS NOT NULL AND u.state<>'' AND LOWER(u.state)=LOWER(me.state) THEN 1 ELSE 0 END same_state,
      CASE WHEN u.gender = CASE me.gender WHEN 'Male' THEN 'Female' WHEN 'Female' THEN 'Male' ELSE u.gender END THEN 1 ELSE 0 END preferred_gender,
      EXISTS(SELECT 1 FROM followers f WHERE f.follower_id=me.id AND f.following_id=u.id) is_following";
}

function discovery_order_sql(): string
{
    return "u.is_demo_user ASC, preferred_gender DESC,
      CASE WHEN distance_km IS NULL THEN 1 ELSE 0 END ASC, distance_km ASC,
      same_city DESC, same_state DESC, u.is_online DESC, u.last_active_at DESC, u.id DESC";
}

function attach_friend_statuses(array $users, int $viewerId): array
{
    foreach ($users as &$user) {
        $user['friend_status'] = friend_status($viewerId, (int)$user['id']);
        unset($user['latitude'], $user['longitude']);
    }
    return $users;
}

