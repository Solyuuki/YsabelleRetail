<?php

namespace Database\Seeders\Catalog\Support;

use Illuminate\Support\Str;

final class CatalogBlueprint
{
    public static function categories(): array
    {
        return [
            self::buildCategory(
                slug: 'running',
                name: 'Running Shoes',
                description: 'Engineered pairs for movement, tempo, and long-mile comfort.',
                sortOrder: 1,
                supplier: 'North Metro Footwear Hub',
                weightGrams: 640,
                defaultSizes: ['7', '8', '9', '10', '11', '12'],
                imagePool: [
                    'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Aurum Runner', 'style_code' => 'YS-AUR-7490', 'price' => 7490, 'compare_offset' => 1500, 'tagline' => 'Featherlight performance runner with carbon-infused energy return', 'story' => 'Engineered mesh and a fast rocker platform keep long-mile efforts smooth.', 'colors' => ['Black/Gold'], 'sizes' => ['7', '8', '9', '10', '11', '12'], 'featured_rank' => 1, 'rating_average' => 4.9, 'review_count' => 184, 'inventory_base' => 24],
                    ['name' => 'Shadow Stride', 'style_code' => 'YS-SHD-6490', 'price' => 6490, 'tagline' => 'Stealth daily trainer with cushioned stability', 'story' => 'A soft heel crash pad and planted midfoot make it dependable for repeat sessions.', 'colors' => ['Black/Graphite'], 'sizes' => ['7', '8', '9', '10', '11'], 'featured_rank' => 2, 'rating_average' => 4.7, 'review_count' => 96, 'inventory_base' => 20],
                    ['name' => 'Azure Velocity', 'style_code' => 'YS-AZV-5790', 'price' => 5790, 'tagline' => 'Cool-toned pace trainer for city loops', 'story' => 'A breezy upper and responsive foam keep tempo efforts lively without bulk.', 'colors' => ['Blue/Black'], 'sizes' => ['7', '8', '9', '10'], 'rating_average' => 4.5, 'review_count' => 73, 'inventory_base' => 18],
                    ['name' => 'Meridian Pace', 'style_code' => 'YS-MRP-6890', 'price' => 6890, 'compare_offset' => 1000, 'tagline' => 'Balanced trainer for steady weekday mileage', 'story' => 'It blends crisp forefoot snap with a calmer heel for commuters who run before work.'],
                    ['name' => 'Cinder Dash', 'style_code' => 'YS-CID-5590', 'price' => 5590, 'tagline' => 'Lightweight runner for short aggressive efforts', 'story' => 'Low stack cushioning and a clean toe spring help quick workouts feel direct.', 'colors' => ['Cinder/Orange']],
                    ['name' => 'Halo Tempo', 'style_code' => 'YS-HLO-6190', 'price' => 6190, 'tagline' => 'Uplifted trainer with soft rebound and airy support', 'story' => 'A sculpted collar and broad landing zone keep pacing work controlled and comfortable.', 'compare_offset' => 900],
                    ['name' => 'Nova Distance', 'style_code' => 'YS-NVD-7090', 'price' => 7090, 'tagline' => 'Long-run pair tuned for high-leg turnover', 'story' => 'It carries an efficient shape with enough foam depth to handle weekend mileage.', 'colors' => ['White/Navy']],
                    ['name' => 'Drift Echo', 'style_code' => 'YS-DRF-5290', 'price' => 5290, 'tagline' => 'Entry performance runner with clean road feel', 'story' => 'Its straightforward midsole and secure tongue setup keep first-time runners confident.'],
                    ['name' => 'Summit Glide', 'style_code' => 'YS-SMG-6590', 'price' => 6590, 'compare_offset' => 900, 'tagline' => 'Well-cushioned cruiser for relaxed weekly volume', 'story' => 'A stable rear platform and moderate flex work well for easy-paced mileage.', 'colors' => ['Moss/Stone']],
                    ['name' => 'Carbon Trace', 'style_code' => 'YS-CBT-7990', 'price' => 7990, 'compare_offset' => 1200, 'tagline' => 'Race-day inspired shoe with a snappy forefoot', 'story' => 'A firmer plate feel and lean build reward runners who want cleaner push-off mechanics.', 'featured_rank' => 3],
                    ['name' => 'Harbor Mile', 'style_code' => 'YS-HBM-5490', 'price' => 5490, 'tagline' => 'Reliable value trainer built for daily road use', 'story' => 'It keeps a smooth heel transition and forgiving upper for casual routine mileage.', 'colors' => ['Slate/Seafoam']],
                    ['name' => 'Aero Cadence', 'style_code' => 'YS-ACD-7390', 'price' => 7390, 'compare_offset' => 1100, 'tagline' => 'Fast-feeling runner with a streamlined lockdown', 'story' => 'A narrow waist and lively toe-off make it a strong option for interval blocks.'],
                    ['name' => 'Solace Run', 'style_code' => 'YS-SLR-5990', 'price' => 5990, 'tagline' => 'Comfort-first trainer for recovery days and long walks', 'story' => 'A softer foam recipe and gentle rocker help tired legs settle into smoother strides.', 'colors' => ['Sand/Platinum']],
                ],
            ),
            self::buildCategory(
                slug: 'sneakers',
                name: 'Sneakers',
                description: 'Premium casual silhouettes for refined off-duty dressing.',
                sortOrder: 2,
                supplier: 'Central Luxe Traders',
                weightGrams: 620,
                defaultSizes: ['6', '7', '8', '9', '10', '11'],
                imagePool: [
                    'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1600269452121-4f2416e55c28?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1514989940723-e8e51635b782?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Ivory Prestige', 'style_code' => 'YS-IVR-5890', 'price' => 5890, 'tagline' => 'Court-inspired low top finished with restrained metallic detail', 'story' => 'Bright leather panels and a clean cupsole keep the pair dressy enough for polished casual fits.', 'colors' => ['Ivory/Gold'], 'sizes' => ['6', '7', '8', '9', '10'], 'featured_rank' => 4, 'rating_average' => 4.8, 'review_count' => 121, 'inventory_base' => 16],
                    ['name' => 'Harbor Court', 'style_code' => 'YS-HBC-5190', 'price' => 5190, 'tagline' => 'Relaxed everyday sneaker with smooth leather layering', 'story' => 'A pared-back profile and cushioned liner make it easy to wear through long city days.'],
                    ['name' => 'Atlas Street', 'style_code' => 'YS-ATS-6390', 'price' => 6390, 'compare_offset' => 900, 'tagline' => 'Structured street sneaker with a sharp sidewall', 'story' => 'It carries firmer foxing, supportive foam, and a premium heel counter for cleaner posture.', 'colors' => ['Black/Gum', 'Stone/Chalk']],
                    ['name' => 'Dune Low', 'style_code' => 'YS-DNL-4890', 'price' => 4890, 'tagline' => 'Soft-toned sneaker with light everyday cushioning', 'story' => 'Minimal seam lines and an easy shape keep the look understated and versatile.', 'colors' => ['Sand/Clay']],
                    ['name' => 'Borough Classic', 'style_code' => 'YS-BRC-4990', 'price' => 4990, 'tagline' => 'Urban essential with a timeless cupsole profile', 'story' => 'Softer collar foam and a durable rubber base make it a dependable weekly rotation shoe.'],
                    ['name' => 'Canvas Note', 'style_code' => 'YS-CVN-4690', 'price' => 4690, 'tagline' => 'Textured canvas sneaker built for laid-back dressing', 'story' => 'The upper breaks in quickly while the trim rubber foxing keeps it crisp.', 'colors' => ['Canvas White/Navy']],
                    ['name' => 'Slate Club', 'style_code' => 'YS-SLC-5590', 'price' => 5590, 'tagline' => 'Tonal leather sneaker with a club-era stance', 'story' => 'Low-key paneling and a slightly raised heel shape give it a dressier lifestyle attitude.', 'compare_offset' => 700],
                    ['name' => 'Riviera Base', 'style_code' => 'YS-RVB-5790', 'price' => 5790, 'tagline' => 'Clean sneaker designed for travel-ready outfits', 'story' => 'Its easy wipe-down upper and resilient footbed suit repeat wear on city breaks.', 'colors' => ['White/Sky']],
                    ['name' => 'Cinder Board', 'style_code' => 'YS-CDB-5290', 'price' => 5290, 'tagline' => 'Low-profile sneaker with a skater-informed edge', 'story' => 'A wider toe shape and flatter underfoot feel make it grounded and stable.'],
                    ['name' => 'Gallery Step', 'style_code' => 'YS-GLS-6090', 'price' => 6090, 'compare_offset' => 900, 'tagline' => 'Refined leather sneaker for gallery-to-dinner styling', 'story' => 'It pairs a denser insole with a sleek profile for all-day polish.', 'featured_rank' => 5],
                    ['name' => 'Metro Crest', 'style_code' => 'YS-MTC-5690', 'price' => 5690, 'tagline' => 'City sneaker with sculpted sidewall and contrast trim', 'story' => 'The build balances easy comfort with a sharper, more directional silhouette.', 'colors' => ['Carbon/Cream']],
                    ['name' => 'Avenue Form', 'style_code' => 'YS-AVF-5390', 'price' => 5390, 'tagline' => 'Modern low top with a soft foam feel underfoot', 'story' => 'A gentle toe spring and lighter cupsole keep it comfortable for long indoor days.'],
                    ['name' => 'Prism Court', 'style_code' => 'YS-PRC-6190', 'price' => 6190, 'compare_offset' => 1000, 'tagline' => 'Premium court sneaker with a glossy accent finish', 'story' => 'It combines smooth full-grain panels with a firmer outsole wrap for structure.', 'colors' => ['Pearl/Gold', 'Black/Bronze']],
                ],
            ),
            self::buildCategory(
                slug: 'basketball-shoes',
                name: 'Basketball Shoes',
                description: 'Court-first silhouettes built for explosive cuts, landings, and containment.',
                sortOrder: 3,
                supplier: 'Summit Athletics Supply',
                weightGrams: 710,
                defaultSizes: ['7', '8', '9', '10', '11', '12'],
                imagePool: [
                    'https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Onyx Vector', 'style_code' => 'YS-ONX-6290', 'price' => 6290, 'tagline' => 'Anchored court shoe with sculpted containment and direct response', 'story' => 'A rigid heel frame and supportive lateral walls help plant harder on defensive slides.', 'colors' => ['Onyx/Graphite'], 'sizes' => ['7', '8', '9', '10', '11'], 'featured_rank' => 6, 'rating_average' => 4.8, 'review_count' => 109, 'inventory_base' => 15],
                    ['name' => 'Crimson Drive', 'style_code' => 'YS-CRD-7190', 'price' => 7190, 'compare_offset' => 1000, 'tagline' => 'Aggressive mid-cut built for first-step burst', 'story' => 'A denser forefoot foam setup and firm shank keep transition energy sharp.', 'colors' => ['Crimson/Black']],
                    ['name' => 'Titan Arc', 'style_code' => 'YS-TAR-8290', 'price' => 8290, 'compare_offset' => 1200, 'tagline' => 'High-output court shoe with strong heel containment', 'story' => 'It leans into explosive takeoffs with a taller support frame and springier front end.'],
                    ['name' => 'Apex Board', 'style_code' => 'YS-APB-7590', 'price' => 7590, 'tagline' => 'Balanced basketball shoe for all-around floor work', 'story' => 'The outsole flexes just enough for guards while still supporting bigger lateral loads.', 'colors' => ['White/Red']],
                    ['name' => 'Eastside Elevate', 'style_code' => 'YS-ESE-6990', 'price' => 6990, 'tagline' => 'Versatile court option for quick two-way play', 'story' => 'A padded ankle opening and broad forefoot base keep it stable through repeated direction changes.'],
                    ['name' => 'Fullcourt Nova', 'style_code' => 'YS-FCN-8790', 'price' => 8790, 'compare_offset' => 1500, 'tagline' => 'Premium game shoe with high-rebound cushioning', 'story' => 'A more energetic foam blend helps landings feel protected without muting push-off.', 'featured_rank' => 7],
                    ['name' => 'Rival Pivot', 'style_code' => 'YS-RVP-7390', 'price' => 7390, 'tagline' => 'Low-cut court pair for fast pivoting guards', 'story' => 'It carries a crisp lateral outrigger and flexible forefoot for quicker recovery steps.', 'colors' => ['Black/Volt']],
                    ['name' => 'Skyline Dunk', 'style_code' => 'YS-SKD-8090', 'price' => 8090, 'compare_offset' => 1000, 'tagline' => 'Cushioned high-top for vertical finishers', 'story' => 'A taller collar and strong heel lift geometry favor hard jumps and direct landings.'],
                    ['name' => 'Baseline Force', 'style_code' => 'YS-BLF-6890', 'price' => 6890, 'tagline' => 'Reliable indoor court shoe with durable side support', 'story' => 'Its rubber wrap and disciplined fit hold up well across repeated practices.'],
                    ['name' => 'Monarch Lift', 'style_code' => 'YS-MNL-8490', 'price' => 8490, 'compare_offset' => 1200, 'tagline' => 'Statement performance shoe with plush game-day ride', 'story' => 'It softens heavy landings while staying stable enough for strong closeouts.', 'colors' => ['Royal/Gold']],
                    ['name' => 'Pulse Rebound', 'style_code' => 'YS-PLR-7790', 'price' => 7790, 'tagline' => 'Reactive court shoe built for second-jump energy', 'story' => 'A springier forefoot setup rewards players who live around the rim.'],
                    ['name' => 'Rally Flight', 'style_code' => 'YS-RFL-7290', 'price' => 7290, 'tagline' => 'Supportive low-top with a compact transition feel', 'story' => 'The underfoot geometry stays low enough for quick guards who like court feel.', 'colors' => ['Silver/Blue']],
                    ['name' => 'Crown Jam', 'style_code' => 'YS-CRJ-8990', 'price' => 8990, 'compare_offset' => 1600, 'tagline' => 'Flagship high-top tuned for playoff-level intensity', 'story' => 'It combines strong heel clip structure with premium foam and tackier traction.', 'featured_rank' => 8],
                ],
            ),
            self::buildCategory(
                slug: 'lifestyle-shoes',
                name: 'Lifestyle Shoes',
                description: 'Relaxed premium silhouettes for city dressing, travel, and all-day wear.',
                sortOrder: 4,
                supplier: 'Cityline Lifestyle Goods',
                weightGrams: 605,
                defaultSizes: ['6', '7', '8', '9', '10', '11'],
                imagePool: [
                    'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1600185365926-3a2ce3cdb9eb?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Maison Drift', 'style_code' => 'YS-MSD-5490', 'price' => 5490, 'tagline' => 'Refined lifestyle runner with tonal depth', 'story' => 'It blends suede-touch panels and a quieter outsole for a softer dressed-down look.'],
                    ['name' => 'Sierra Form', 'style_code' => 'YS-SRF-5190', 'price' => 5190, 'tagline' => 'Casual modern trainer with a balanced everyday fit', 'story' => 'Its padded collar and understated upper make it easy to style with denim or trousers.'],
                    ['name' => 'Olive District', 'style_code' => 'YS-OLD-4990', 'price' => 4990, 'tagline' => 'Earth-toned daily pair built around versatile comfort', 'story' => 'Soft inner foam and a calm outsole profile make it a natural desk-to-dinner option.', 'colors' => ['Olive/Sand']],
                    ['name' => 'Wren Leisure', 'style_code' => 'YS-WRL-5290', 'price' => 5290, 'tagline' => 'Easygoing casual shoe with a smoother toe line', 'story' => 'It favors softer materials and lighter foxing for laid-back daily wear.'],
                    ['name' => 'District Muse', 'style_code' => 'YS-DSM-5790', 'price' => 5790, 'compare_offset' => 700, 'tagline' => 'City lifestyle pair with clean layered texture', 'story' => 'The build leans premium without becoming too formal, making it flexible across outfits.'],
                    ['name' => 'Velvet Lane', 'style_code' => 'YS-VEL-5690', 'price' => 5690, 'tagline' => 'Soft-finish lifestyle sneaker with understated contrast', 'story' => 'A plush liner and gentler sidewall shape keep the feel warm and easy.', 'colors' => ['Espresso/Taupe']],
                    ['name' => 'Montrose Easy', 'style_code' => 'YS-MTE-5390', 'price' => 5390, 'tagline' => 'Travel-friendly casual pair that packs cleanly', 'story' => 'Its lighter chassis and easy break-in make it a dependable carry-on sneaker.'],
                    ['name' => 'Social Standard', 'style_code' => 'YS-SSD-4890', 'price' => 4890, 'tagline' => 'Simple everyday shoe with a polished upper finish', 'story' => 'The model strips away bulky overlays to keep outfits looking sharper.'],
                    ['name' => 'Camden Daily', 'style_code' => 'YS-CMD-5590', 'price' => 5590, 'tagline' => 'Daily-wear essential with roomy forefoot comfort', 'story' => 'A slightly broader shape helps it stay comfortable on longer city days.'],
                    ['name' => 'Marble Set', 'style_code' => 'YS-MBS-6190', 'price' => 6190, 'compare_offset' => 900, 'tagline' => 'Elevated lifestyle model with crisp sculpted sidewalls', 'story' => 'It offers a little more structure and a cleaner dress-casual stance.', 'colors' => ['Stone/Marble']],
                    ['name' => 'Northline Ease', 'style_code' => 'YS-NLE-5290', 'price' => 5290, 'tagline' => 'Relaxed commuter shoe with soft landings and light weight', 'story' => 'The tread stays understated while the footbed remains forgiving for all-day walking.'],
                    ['name' => 'Ember Avenue', 'style_code' => 'YS-EMA-5990', 'price' => 5990, 'compare_offset' => 800, 'tagline' => 'Premium city sneaker with warm contrast accents', 'story' => 'A richer material mix and dressed-up midsole shape help it stand out without shouting.', 'colors' => ['Mahogany/Cream']],
                    ['name' => 'Studio Parade', 'style_code' => 'YS-STP-6290', 'price' => 6290, 'compare_offset' => 900, 'tagline' => 'Statement lifestyle pair for creative-office dressing', 'story' => 'The upper lines stay clean while the sole profile carries a stronger visual identity.'],
                ],
            ),
            self::buildCategory(
                slug: 'training-shoes',
                name: 'Training Shoes',
                description: 'Stable trainers for gym sessions, circuits, lifting, and fast mixed movement.',
                sortOrder: 5,
                supplier: 'Ironline Performance Goods',
                weightGrams: 655,
                defaultSizes: ['7', '8', '9', '10', '11', '12'],
                imagePool: [
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1514989940723-e8e51635b782?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Volt Edge', 'style_code' => 'YS-VLT-5790', 'price' => 5790, 'compare_offset' => 900, 'tagline' => 'Responsive trainer with visible grip and high-energy accents', 'story' => 'A flatter heel and secure midfoot keep reps, short runs, and machine work controlled.', 'colors' => ['Graphite/Volt'], 'sizes' => ['7', '8', '9', '10', '11'], 'rating_average' => 4.5, 'review_count' => 88, 'inventory_base' => 14],
                    ['name' => 'Core Circuit', 'style_code' => 'YS-CRC-5490', 'price' => 5490, 'tagline' => 'Gym trainer with planted heel support and flex grooves', 'story' => 'It stays grounded for lifting while still moving well through circuit transitions.'],
                    ['name' => 'Forge Rep', 'style_code' => 'YS-FGR-6890', 'price' => 6890, 'compare_offset' => 900, 'tagline' => 'Stable cross-trainer designed for repeat strength blocks', 'story' => 'A firmer rear platform and supportive sidewalls help under loaded work.', 'colors' => ['Black/Red']],
                    ['name' => 'Iron Pulse', 'style_code' => 'YS-IRP-6290', 'price' => 6290, 'tagline' => 'Dense gym shoe for steady lower-body sessions', 'story' => 'Its flatter geometry improves planted feel for sled pushes and weighted squats.'],
                    ['name' => 'Momentum Lab', 'style_code' => 'YS-MML-7090', 'price' => 7090, 'compare_offset' => 1000, 'tagline' => 'Premium training shoe with quick transition energy', 'story' => 'A more reactive forefoot balances short cardio bursts with stable landings.'],
                    ['name' => 'Atlas Flex', 'style_code' => 'YS-ATF-6590', 'price' => 6590, 'tagline' => 'Flexible trainer for studio classes and mixed movement', 'story' => 'It bends more naturally through toe-off while keeping the arch well supported.'],
                    ['name' => 'Cinder Row', 'style_code' => 'YS-CDR-5290', 'price' => 5290, 'tagline' => 'Workout shoe tuned for rowers, ropes, and bodyweight work', 'story' => 'Lower stack height helps it feel connected during faster directional changes.', 'colors' => ['Charcoal/Orange']],
                    ['name' => 'Rack Sprint', 'style_code' => 'YS-RKS-6190', 'price' => 6190, 'tagline' => 'Training model that balances stable lifting with short turf sprints', 'story' => 'Its outsole pattern grips well on gym flooring without feeling too rigid.'],
                    ['name' => 'Prime Interval', 'style_code' => 'YS-PMI-6790', 'price' => 6790, 'compare_offset' => 900, 'tagline' => 'Fast-response trainer for interval-driven gym sessions', 'story' => 'The forefoot pops a little more while the heel stays disciplined and grounded.'],
                    ['name' => 'Shift Mode', 'style_code' => 'YS-SHM-5690', 'price' => 5690, 'tagline' => 'All-round training shoe with a narrow secure waist', 'story' => 'A close-fitting chassis makes it feel confident in side-to-side movement drills.'],
                    ['name' => 'Torque Frame', 'style_code' => 'YS-TQF-6490', 'price' => 6490, 'compare_offset' => 800, 'tagline' => 'Structured trainer with locked-in midfoot support', 'story' => 'Its denser frame adds reassurance for dynamic warmups and light lifting.'],
                    ['name' => 'Studio Lift', 'style_code' => 'YS-STL-5990', 'price' => 5990, 'tagline' => 'Compact training shoe designed for smaller studio spaces', 'story' => 'It keeps transitions tidy, with controlled flex and a quieter aesthetic.', 'colors' => ['Stone/Black']],
                    ['name' => 'Endurance Set', 'style_code' => 'YS-END-7190', 'price' => 7190, 'compare_offset' => 1000, 'tagline' => 'High-capacity trainer for longer mixed-modality sessions', 'story' => 'More cushioning depth lets it handle longer conditioning blocks without becoming unstable.'],
                ],
            ),
            self::buildCategory(
                slug: 'walking-shoes',
                name: 'Walking Shoes',
                description: 'Comfort-led walking shoes with smooth transitions, softer collars, and all-day support.',
                sortOrder: 6,
                supplier: 'Daily Motion Footcare',
                weightGrams: 600,
                defaultSizes: ['6', '7', '8', '9', '10', '11'],
                imagePool: [
                    'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Daybreak Stroll', 'style_code' => 'YS-DBS-4790', 'price' => 4790, 'tagline' => 'Soft walker for gentle daily errands and commutes', 'story' => 'Its easy rocker and forgiving heel foam reduce the harshness of long standing periods.'],
                    ['name' => 'Harbor Walker', 'style_code' => 'YS-HBW-5190', 'price' => 5190, 'tagline' => 'Comfort walking shoe with padded collar support', 'story' => 'A broader sole platform gives it a more secure everyday feel.', 'colors' => ['Navy/Silver']],
                    ['name' => 'Cloud Avenue', 'style_code' => 'YS-CLA-5590', 'price' => 5590, 'compare_offset' => 700, 'tagline' => 'Cushioned walking pair with softer step-in comfort', 'story' => 'The plush top line and smoother heel entry make it a natural all-day choice.'],
                    ['name' => 'Gentle Mile', 'style_code' => 'YS-GTM-4890', 'price' => 4890, 'tagline' => 'Simple walker with quiet low-impact cushioning', 'story' => 'Its tuned-down profile works well for workdays that involve constant movement.'],
                    ['name' => 'Metro Comfort', 'style_code' => 'YS-MCF-4590', 'price' => 4590, 'tagline' => 'Value walking shoe with easy flexibility through the forefoot', 'story' => 'The foam package stays soft without turning mushy under repeated use.'],
                    ['name' => 'Trailway Ease', 'style_code' => 'YS-TWE-5390', 'price' => 5390, 'tagline' => 'Walking shoe with a little extra grip for mixed surfaces', 'story' => 'It keeps the ride calm while giving more confidence on rougher sidewalks.'],
                    ['name' => 'Urban Roam', 'style_code' => 'YS-URR-5790', 'price' => 5790, 'compare_offset' => 800, 'tagline' => 'City walker with breathable support and durable outsole rubber', 'story' => 'It balances lightness and longevity for heavier weekly use.', 'colors' => ['Grey/White']],
                    ['name' => 'Brook Step', 'style_code' => 'YS-BKS-4990', 'price' => 4990, 'tagline' => 'Supportive walker with a calmer low-profile stance', 'story' => 'The shoe sits lower to the ground for people who prefer stable everyday footing.'],
                    ['name' => 'Quiet Motion', 'style_code' => 'YS-QTM-5690', 'price' => 5690, 'compare_offset' => 700, 'tagline' => 'Smooth walker with a gently guided heel transition', 'story' => 'A rounded rear shape keeps long shifts feeling less abrupt.'],
                    ['name' => 'Pebble Pace', 'style_code' => 'YS-PBP-4490', 'price' => 4490, 'tagline' => 'Flexible entry walking shoe with dependable comfort', 'story' => 'Its lighter structure and easy ride suit errands and casual day trips.'],
                    ['name' => 'Dawn Cruiser', 'style_code' => 'YS-DCR-5990', 'price' => 5990, 'compare_offset' => 700, 'tagline' => 'Premium walking shoe built for extended casual wear', 'story' => 'It adds more foam depth and a tidier upper finish for customers who want comfort with polish.'],
                    ['name' => 'Softstride City', 'style_code' => 'YS-SCT-5290', 'price' => 5290, 'tagline' => 'Everyday walker with soft landings and a roomy toe box', 'story' => 'It favors relaxed fit and gentle cushioning over aggressive performance.'],
                    ['name' => 'Lane Support', 'style_code' => 'YS-LNS-5490', 'price' => 5490, 'tagline' => 'Support walker with broad underfoot contact', 'story' => 'A wider contact base helps it feel confident over long paved routes.', 'colors' => ['Taupe/White']],
                ],
            ),
            self::buildCategory(
                slug: 'slip-ons',
                name: 'Slip-ons',
                description: 'Easy-on silhouettes for fast routines, travel days, and low-effort dressing.',
                sortOrder: 7,
                supplier: 'Harbor Casual Supply',
                weightGrams: 540,
                defaultSizes: ['6', '7', '8', '9', '10', '11'],
                imagePool: [
                    'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1600269452121-4f2416e55c28?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1514989940723-e8e51635b782?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Laguna Slip', 'style_code' => 'YS-LGS-4190', 'price' => 4190, 'tagline' => 'Light slip-on for quick errands and travel days', 'story' => 'Stretch panels and a soft footbed keep entry effortless and all-day wear relaxed.'],
                    ['name' => 'Portside Ease', 'style_code' => 'YS-PSE-4390', 'price' => 4390, 'tagline' => 'Simple low-profile slip-on with clean resort styling', 'story' => 'The upper stays minimal while the outsole remains durable enough for frequent casual wear.'],
                    ['name' => 'Willow Slide', 'style_code' => 'YS-WLS-4690', 'price' => 4690, 'tagline' => 'Textured slip-on with cushioned insole support', 'story' => 'A little more underfoot padding makes it easy to wear through long laid-back days.', 'colors' => ['Willow/Tan']],
                    ['name' => 'Sunday Coast', 'style_code' => 'YS-SDC-4490', 'price' => 4490, 'tagline' => 'Weekend slip-on with a clean coastal finish', 'story' => 'A flexible forefoot and softer collar edge keep it unstructured and comfortable.'],
                    ['name' => 'Deckline Knit', 'style_code' => 'YS-DKT-4990', 'price' => 4990, 'compare_offset' => 600, 'tagline' => 'Knit slip-on with breathable easy-on comfort', 'story' => 'Its airy upper and flexible sidewalls work especially well in warm-weather routines.'],
                    ['name' => 'Harbor Slip Knit', 'style_code' => 'YS-HSK-5290', 'price' => 5290, 'tagline' => 'Premium knit slip-on for travel and casual office days', 'story' => 'The more refined knit pattern keeps it comfortable without looking too athletic.'],
                    ['name' => 'Quiet Cove', 'style_code' => 'YS-QCV-4590', 'price' => 4590, 'tagline' => 'Soft-wearing slip-on with a calm tonal finish', 'story' => 'Its padded footbed and rounded toe make it especially easy for repeat wear.'],
                    ['name' => 'Drift On', 'style_code' => 'YS-DFT-4790', 'price' => 4790, 'tagline' => 'Everyday slip-on built for fast on-and-off use', 'story' => 'The heel entry stays forgiving while the outsole remains sturdy enough for urban use.'],
                    ['name' => 'Ease Loafer Sport', 'style_code' => 'YS-ELS-5490', 'price' => 5490, 'compare_offset' => 700, 'tagline' => 'Hybrid slip-on with loafer shape and sport comfort', 'story' => 'It bridges dress-casual outfits with a more relaxed underfoot feel.', 'colors' => ['Espresso/Black']],
                    ['name' => 'Seabound Flex', 'style_code' => 'YS-SBF-4890', 'price' => 4890, 'tagline' => 'Flexible slip-on that moves naturally through the forefoot', 'story' => 'Its lighter sole and clean topline work well as a packable travel option.'],
                    ['name' => 'Metro Slip', 'style_code' => 'YS-MTS-5190', 'price' => 5190, 'tagline' => 'City-ready slip-on with a tidier sidewall and denser insole', 'story' => 'The shape looks sharper than a standard lounge shoe while staying easy to wear.'],
                    ['name' => 'Hourglass Step', 'style_code' => 'YS-HGS-5390', 'price' => 5390, 'compare_offset' => 700, 'tagline' => 'Dressier slip-on with a tapered profile and soft landing', 'story' => 'It brings smarter proportions to a relaxed easy-entry build.'],
                    ['name' => 'Canvas Harbor', 'style_code' => 'YS-CVH-4290', 'price' => 4290, 'tagline' => 'Canvas slip-on with straightforward casual appeal', 'story' => 'Simple texture and lighter build make it a dependable warm-weather essential.'],
                ],
            ),
            self::buildCategory(
                slug: 'boots-high-cut',
                name: 'Boots / High-cut Shoes',
                description: 'High-cut footwear with stronger coverage, rugged traction, and colder-weather versatility.',
                sortOrder: 8,
                supplier: 'Frontier Trail Outfitters',
                weightGrams: 820,
                defaultSizes: ['7', '8', '9', '10', '11', '12'],
                imagePool: [
                    'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?auto=format&fit=crop&w=1400&q=80',
                    'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1400&q=80',
                ],
                products: [
                    ['name' => 'Terra High', 'style_code' => 'YS-TRH-5290', 'price' => 5290, 'tagline' => 'Everyday high-cut with durable sidewall coverage', 'story' => 'It keeps the silhouette rugged enough for casual outdoor use while staying approachable for daily styling.', 'colors' => ['Tan/Chestnut']],
                    ['name' => 'Summit Forge', 'style_code' => 'YS-SMF-7490', 'price' => 7490, 'compare_offset' => 900, 'tagline' => 'Protective mid boot for mixed terrain and city rain', 'story' => 'A firmer outsole and padded collar support longer wear on rougher surfaces.'],
                    ['name' => 'Ridge Patrol', 'style_code' => 'YS-RDP-7890', 'price' => 7890, 'tagline' => 'Supportive field boot with strong heel hold', 'story' => 'It leans on durable overlays and tougher tread for more rugged daily use.'],
                    ['name' => 'Ember Trek', 'style_code' => 'YS-EMT-6990', 'price' => 6990, 'compare_offset' => 800, 'tagline' => 'Trail-minded high-cut with warmer visual finish', 'story' => 'The build balances protection and flexibility so it does not feel overbuilt.'],
                    ['name' => 'Canyon Guard', 'style_code' => 'YS-CYG-7690', 'price' => 7690, 'tagline' => 'Protective boot with deeper lug confidence', 'story' => 'Its rubber package favors grip and durability for heavier regular use.', 'colors' => ['Olive/Brown']],
                    ['name' => 'Atlas Highstreet', 'style_code' => 'YS-AHS-6390', 'price' => 6390, 'tagline' => 'Urban high-cut with a cleaner lifestyle-boot balance', 'story' => 'The shoe keeps enough structure for cooler weather without becoming too technical.'],
                    ['name' => 'Northwall Boot', 'style_code' => 'YS-NWB-8590', 'price' => 8590, 'compare_offset' => 1200, 'tagline' => 'Premium high-cut with sturdier all-weather detailing', 'story' => 'It adds more weather resistance, firmer sidewall structure, and a premium upper finish.'],
                    ['name' => 'Outpost Rise', 'style_code' => 'YS-OPR-7290', 'price' => 7290, 'tagline' => 'Mid-height boot for commuting in wet and rough conditions', 'story' => 'A stable heel base and more aggressive outsole make it practical and easy to trust.'],
                    ['name' => 'Timber Crest', 'style_code' => 'YS-TMC-7790', 'price' => 7790, 'compare_offset' => 900, 'tagline' => 'Structured boot with plush ankle padding and deeper traction', 'story' => 'It offers rugged styling with enough cushioning for all-day wear.'],
                    ['name' => 'Granite Hike', 'style_code' => 'YS-GRH-8190', 'price' => 8190, 'tagline' => 'Outdoor-oriented high-cut with stronger underfoot protection', 'story' => 'Denser cushioning and a tougher outsole setup help it handle longer route days.'],
                    ['name' => 'Pioneer Field', 'style_code' => 'YS-PNF-6890', 'price' => 6890, 'tagline' => 'Workhorse high-cut built around durable upper support', 'story' => 'Its straightforward construction keeps the look classic while staying genuinely useful.'],
                    ['name' => 'Alpine Relay', 'style_code' => 'YS-ALR-8390', 'price' => 8390, 'compare_offset' => 1100, 'tagline' => 'Premium boot with modern collar comfort and firmer trail grip', 'story' => 'It carries a little more performance DNA into a polished all-weather upper.'],
                    ['name' => 'Dune Ascent', 'style_code' => 'YS-DAS-7090', 'price' => 7090, 'tagline' => 'Versatile high-cut with a lighter faster-wearing chassis', 'story' => 'A more flexible build makes it less bulky while preserving reassuring coverage.'],
                ],
            ),
        ];
    }

    private static function buildCategory(
        string $slug,
        string $name,
        string $description,
        int $sortOrder,
        string $supplier,
        int $weightGrams,
        array $defaultSizes,
        array $imagePool,
        array $products,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'sort_order' => $sortOrder,
            'products' => collect($products)
                ->values()
                ->map(fn (array $product, int $index): array => self::buildProduct(
                    product: $product,
                    categoryName: $name,
                    supplier: $supplier,
                    weightGrams: $weightGrams,
                    defaultSizes: $defaultSizes,
                    imagePool: $imagePool,
                    productIndex: $index,
                ))
                ->all(),
        ];
    }

    private static function buildProduct(
        array $product,
        string $categoryName,
        string $supplier,
        int $weightGrams,
        array $defaultSizes,
        array $imagePool,
        int $productIndex,
    ): array {
        $colors = $product['colors'] ?? [self::defaultColorFor($categoryName, $productIndex)];
        $sizes = $product['sizes'] ?? $defaultSizes;
        $primaryImage = $imagePool[$product['image_slot'] ?? ($productIndex % count($imagePool))];

        return [
            'name' => $product['name'],
            'style_code' => $product['style_code'],
            'base_price' => $product['price'],
            'compare_at_price' => isset($product['compare_offset']) ? $product['price'] + $product['compare_offset'] : null,
            'rating_average' => $product['rating_average'] ?? self::ratingFor($productIndex),
            'review_count' => $product['review_count'] ?? (54 + ($productIndex * 17)),
            'is_featured' => isset($product['featured_rank']),
            'featured_rank' => $product['featured_rank'] ?? null,
            'short_description' => rtrim($product['tagline'], '.').'.',
            'description' => "{$product['name']} is a {$categoryName} style designed for {$product['story']}",
            'primary_image_url' => $primaryImage,
            'image_alt' => "{$product['name']} {$categoryName} product image",
            'image_gallery' => self::galleryFor($imagePool, $productIndex),
            'variants' => self::buildVariants(
                product: $product,
                supplier: $supplier,
                weightGrams: $weightGrams,
                sizes: $sizes,
                colors: $colors,
                productIndex: $productIndex,
            ),
        ];
    }

    private static function buildVariants(
        array $product,
        string $supplier,
        int $weightGrams,
        array $sizes,
        array $colors,
        int $productIndex,
    ): array {
        $variants = [];
        $multiColor = count($colors) > 1;

        foreach ($colors as $colorIndex => $color) {
            foreach ($sizes as $sizeIndex => $size) {
                $variants[] = [
                    'name' => $multiColor ? "Size {$size} / {$color}" : "Size {$size}",
                    'sku' => self::skuFor($product['style_code'], $size, $color, $multiColor),
                    'barcode' => self::barcodeFor($productIndex, $sizeIndex, $colorIndex),
                    'option_values' => [
                        'size' => $size,
                        'color' => $color,
                    ],
                    'price' => $product['price'],
                    'compare_at_price' => isset($product['compare_offset']) ? $product['price'] + $product['compare_offset'] : null,
                    'cost_price' => round($product['price'] * (0.56 + (($productIndex + $colorIndex) % 4) * 0.02), 2),
                    'supplier_name' => $supplier,
                    'weight_grams' => $weightGrams + ($sizeIndex * 8) + ($colorIndex * 5),
                    'status' => 'active',
                    'inventory' => self::inventoryFor(
                        product: $product,
                        productIndex: $productIndex,
                        sizeIndex: $sizeIndex,
                        colorIndex: $colorIndex,
                    ),
                ];
            }
        }

        return $variants;
    }

    private static function galleryFor(array $imagePool, int $productIndex): array
    {
        $first = $imagePool[$productIndex % count($imagePool)];
        $second = $imagePool[($productIndex + 1) % count($imagePool)];

        return array_values(array_unique([$first, $second]));
    }

    private static function defaultColorFor(string $categoryName, int $productIndex): string
    {
        $palettes = [
            'Running Shoes' => ['Black/Gold', 'Blue/Black', 'White/Navy', 'Slate/Mist', 'Sand/Platinum'],
            'Sneakers' => ['White/Gum', 'Black/Cream', 'Stone/Taupe', 'Ivory/Gold', 'Carbon/Cream'],
            'Basketball Shoes' => ['Black/Red', 'Onyx/Graphite', 'Royal/Gold', 'White/Blue', 'Crimson/Black'],
            'Lifestyle Shoes' => ['Stone/Cream', 'Espresso/Taupe', 'Olive/Sand', 'Mahogany/Cream', 'Grey/White'],
            'Training Shoes' => ['Graphite/Volt', 'Black/Red', 'Stone/Black', 'Slate/Orange', 'Silver/White'],
            'Walking Shoes' => ['Grey/White', 'Navy/Silver', 'Taupe/White', 'Black/Grey', 'Rose/Stone'],
            'Slip-ons' => ['Sand/White', 'Espresso/Black', 'Navy/White', 'Olive/Tan', 'Charcoal/Cream'],
            'Boots / High-cut Shoes' => ['Tan/Chestnut', 'Olive/Brown', 'Black/Charcoal', 'Sand/Ochre', 'Mahogany/Stone'],
        ];

        $options = $palettes[$categoryName] ?? ['Black/Gold'];

        return $options[$productIndex % count($options)];
    }

    private static function ratingFor(int $productIndex): float
    {
        $ratings = [4.5, 4.6, 4.7, 4.8, 4.9];

        return $ratings[$productIndex % count($ratings)];
    }

    private static function skuFor(string $styleCode, string $size, string $color, bool $multiColor): string
    {
        if (! $multiColor) {
            return "{$styleCode}-{$size}";
        }

        return "{$styleCode}-".self::colorCode($color)."-{$size}";
    }

    private static function colorCode(string $color): string
    {
        $parts = preg_split('/[^a-z0-9]+/i', Str::upper($color)) ?: [];

        return collect($parts)
            ->filter()
            ->map(fn (string $part): string => Str::take($part, 3))
            ->implode('');
    }

    private static function barcodeFor(int $productIndex, int $sizeIndex, int $colorIndex): string
    {
        return (string) (8800000000000 + ($productIndex * 100) + ($colorIndex * 10) + $sizeIndex + 1);
    }

    private static function inventoryFor(array $product, int $productIndex, int $sizeIndex, int $colorIndex): array
    {
        if (isset($product['inventory_base'])) {
            $quantityOnHand = $product['inventory_base'] + (($sizeIndex + $colorIndex) % 5);
        } else {
            $quantityOnHand = 7 + (($productIndex * 5 + $sizeIndex * 3 + $colorIndex * 2) % 18);

            if ((($productIndex + 1) * ($sizeIndex + 2) + $colorIndex) % 19 === 0) {
                $quantityOnHand = 0;
            } elseif ((($productIndex + 1) + $sizeIndex + $colorIndex) % 11 === 0) {
                $quantityOnHand = 2;
            } elseif ((($productIndex + 2) + ($sizeIndex * 2) + $colorIndex) % 13 === 0) {
                $quantityOnHand = 4;
            }
        }

        $reservedQuantity = $quantityOnHand >= 12 ? (($productIndex + $sizeIndex + $colorIndex) % 2) : 0;
        $reorderLevel = max(3, min(6, $quantityOnHand === 0 ? 4 : (int) ceil(max($quantityOnHand, 6) / 5)));

        return [
            'quantity_on_hand' => $quantityOnHand,
            'reserved_quantity' => $reservedQuantity,
            'reorder_level' => $reorderLevel,
            'allow_backorder' => false,
        ];
    }
}
