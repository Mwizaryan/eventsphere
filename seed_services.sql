-- seed_services.sql
-- 6 realistic test entries for the services table.
-- Run this in phpMyAdmin: event_site_db > SQL tab > paste & execute.

INSERT INTO services (category, title, description, price, image_url) VALUES

-- Venues
('venue',
 'The Rosewood Grand Ballroom',
 'An opulent 10,000 sq ft ballroom adorned with crystal chandeliers, Italian marble floors, and floor-to-ceiling windows overlooking manicured gardens. Accommodates up to 500 guests with full AV, lighting rigs, and a dedicated bridal suite.',
 4500.00,
 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?w=800&q=80'),

('venue',
 'Skyline Terrace at The Meridian',
 'A breathtaking rooftop venue perched 32 floors above the city skyline. Features an open-air deck, retractable glass canopy, private elevator access, and panoramic views. Ideal for cocktail receptions and intimate corporate galas up to 200 guests.',
 3200.00,
 'https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?w=800&q=80'),

-- Entertainers
('entertainer',
 'The Velvet Jazz Collective',
 'A Grammy-nominated six-piece jazz ensemble bringing sophisticated swing, smooth bossa nova, and soulful blues to your event. Available for cocktail hours, dinner sets, and full evening performances. Repertoire spans 400+ curated songs across five decades.',
 2800.00,
 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=800&q=80'),

('entertainer',
 'DJ Axiom — Premium Live Set',
 'One of the region''s most sought-after event DJs, specializing in seamless blends across house, R&B, and top-40. Full touring-grade rig includes a 20,000W sound system, intelligent lighting, and a custom LED booth. Keeps any dancefloor packed from first song to last call.',
 1800.00,
 'https://images.unsplash.com/photo-1571266028243-e4733b0f0bb0?w=800&q=80'),

-- Catering
('catering',
 'Élite Culinary — Farm-to-Table Banquet',
 'Michelin-trained chefs craft a bespoke five-course tasting menu using locally sourced, seasonal ingredients. Service includes full front-of-house staff, sommelier-paired wine selection, custom menu cards, and elegant plated or family-style presentation for up to 300 guests.',
 8500.00,
 'https://images.unsplash.com/photo-1555244162-803834f70033?w=800&q=80'),

('catering',
 'The Grand Buffet Experience by Harvest Table Co.',
 'A lavish, chef-attended buffet featuring global cuisine stations — from a live carving station and sushi bar to artisan dessert displays and a custom cocktail garden. All-inclusive pricing covers setup, staffing, premium tableware, and post-event cleanup for up to 250 guests.',
 5200.00,
 'https://images.unsplash.com/photo-1567521464027-f127ff144326?w=800&q=80');
