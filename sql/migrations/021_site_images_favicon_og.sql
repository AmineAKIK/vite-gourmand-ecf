-- 021_site_images_favicon_og.sql
-- Sépare favicon et og:image du logo navbar
INSERT INTO site_image (cle, url) VALUES
    ('favicon', ''),
    ('og_image', '')
ON DUPLICATE KEY UPDATE url = VALUES(url);
