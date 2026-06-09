<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?>
<?php
    $loc = $page->is_home ? $base : $base.'/'.$page->slug;
    $lastmod = $page->updated_at?->toAtomString();
    $priority = $page->is_home ? '1.0' : '0.8';
?>
    <url>
        <loc><?= htmlspecialchars($loc, ENT_XML1) ?></loc>
<?php if ($lastmod): ?>
        <lastmod><?= $lastmod ?></lastmod>
<?php endif; ?>
        <changefreq>weekly</changefreq>
        <priority><?= $priority ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
