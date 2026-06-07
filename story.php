<?php
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/seo.php';
require_once __DIR__ . '/api/helpers/story.php';
$slug = preg_replace('/[^a-zA-Z0-9-]/', '', $_GET['slug'] ?? '');
$stmt = db()->prepare("SELECT s.*,a.name author_name,a.admin_code,(SELECT COALESCE(SUM(view_count),0) FROM story_views v WHERE v.story_id=s.id) views_count FROM stories s JOIN admin_users a ON a.id=s.admin_id WHERE s.slug=? AND " . story_is_public_sql('s') . ' LIMIT 1');
$stmt->execute([$slug]);
$story = $stmt->fetch();
if (!$story) { http_response_code(404); echo '<!doctype html><title>Story not found</title><h1>Story not found</h1>'; exit; }
$base = site_url(); $canonical = $base . '/stories/' . rawurlencode($story['slug']);
$title = htmlspecialchars($story['meta_title'] ?: $story['title']); $description = htmlspecialchars($story['meta_description'] ?: $story['excerpt']);
$image = $story['featured_image'] ? $base . '/' . ltrim($story['featured_image'], '/') : '';
$article = ['@context'=>'https://schema.org','@type'=>'Article','headline'=>$story['title'],'description'=>$story['meta_description'],'author'=>['@type'=>'Person','name'=>$story['author_name']],'datePublished'=>date('c', strtotime($story['published_at'])),'dateModified'=>date('c', strtotime($story['updated_at'])),'mainEntityOfPage'=>$canonical,'wordCount'=>str_word_count(strip_tags($story['content']))];
if ($image) $article['image'] = [$image];
$breadcrumb = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$base.'/'],['@type'=>'ListItem','position'=>2,'name'=>'Stories','item'=>$base.'/stories'],['@type'=>'ListItem','position'=>3,'name'=>$story['title'],'item'=>$canonical]]];
$related = db()->prepare("SELECT title,slug FROM stories WHERE id<>? AND " . story_is_public_sql('stories') . " AND category=? ORDER BY published_at DESC LIMIT 6");
$related->execute([$story['id'], $story['category']]);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> - myself</title><meta name="description" content="<?= $description ?>"><meta name="robots" content="index, follow, max-image-preview:large"><link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
<meta property="og:type" content="article"><meta property="og:title" content="<?= $title ?>"><meta property="og:description" content="<?= $description ?>"><meta property="og:url" content="<?= htmlspecialchars($canonical) ?>"><?php if($image):?><meta property="og:image" content="<?=htmlspecialchars($image)?>"><?php endif;?>
<meta name="twitter:card" content="<?= $image?'summary_large_image':'summary' ?>"><meta name="twitter:title" content="<?= $title ?>"><meta name="twitter:description" content="<?= $description ?>"><?php if($image):?><meta name="twitter:image" content="<?=htmlspecialchars($image)?>"><?php endif;?>
<script type="application/ld+json"><?=json_encode($article,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script><script type="application/ld+json"><?=json_encode($breadcrumb,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
<style>body{margin:0;background:#f7f8fb;color:#171717;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:850px;margin:auto;padding:24px}.card{background:#fff;border:1px solid #e6e8ef;border-radius:18px;padding:clamp(18px,4vw,42px)}a{color:inherit}.crumbs,.byline{color:#71717a;font-size:14px}.crumbs{margin:18px 0}.cover{width:100%;border-radius:14px;margin:20px 0}.content{font-size:18px;line-height:1.8}.content h2,.content h3{line-height:1.2;margin-top:32px}.related{display:grid;gap:10px;margin-top:20px}.related a{background:#fff;border:1px solid #e6e8ef;border-radius:12px;padding:14px}.login{margin-top:24px;padding:18px;border:1px dashed #cbd5e1;border-radius:12px}</style></head>
<body><main class="wrap"><a href="<?=$base?>/public"><b>myself</b></a><nav class="crumbs"><a href="<?=$base?>/public">Home</a> / <a href="<?=$base?>/public/stories">Stories</a> / <?=$title?></nav><article class="card"><span><?=htmlspecialchars($story['category'])?></span><h1><?=$title?></h1><p class="byline">Published by <?=htmlspecialchars($story['author_name'])?> · <?=htmlspecialchars($story['admin_code'])?> · <?=date('M d, Y',strtotime($story['published_at']))?> · <?=story_reading_minutes($story['content'])?> min read · <?=(int)$story['views_count']?> views</p><?php if($image):?><img class="cover" loading="eager" src="<?=htmlspecialchars($image)?>" alt="<?=$title?>"><?php endif;?><?php if($story['audio_path']):?><audio controls preload="metadata" src="<?=htmlspecialchars($base.'/'.$story['audio_path'])?>"></audio><?php endif;?><div class="content"><?=$story['content']?></div><div class="login"><a href="<?=$base?>/public/login">Login</a> to like, react, and join the discussion.</div></article><section class="related"><h2>Related Stories</h2><?php foreach($related->fetchAll() as $r):?><a href="<?=$base?>/stories/<?=rawurlencode($r['slug'])?>"><?=htmlspecialchars($r['title'])?></a><?php endforeach;?></section></main></body></html>

