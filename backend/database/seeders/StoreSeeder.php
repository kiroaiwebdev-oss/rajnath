<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starting product list for the store: the nine Navratna stones, popular uparatna, rudraksha,
 * yantra, mala and pooja kits, with original descriptions and typical starting prices. Every item
 * is created HIDDEN with stock 0: a real stone has its own weight, origin, lab certificate and
 * photo, so the owner fills those in Admin › Gem store and switches it on sale. Existing slugs are
 * never touched.
 */
class StoreSeeder extends Seeder
{
    /** [category, name, planet / use, summary, starting price ₹, colour for the illustration] */
    public const ITEMS = [
        ['gemstone', 'Ruby (Manik)', 'Sun', 'For a well-placed Sun: confidence, leadership and recognition, worn only after a chart check.', 12000, '#c0263b'],
        ['gemstone', 'Pearl (Moti)', 'Moon', 'Natural pearl for the Moon: calm mind, emotional balance and good sleep, after chart analysis.', 3500, '#f4efe4'],
        ['gemstone', 'Red Coral (Moonga)', 'Mars', 'Italian or Japanese red coral for Mars: courage, energy and property matters.', 4500, '#d94a2b'],
        ['gemstone', 'Emerald (Panna)', 'Mercury', 'Colombian or Zambian emerald for Mercury: speech, learning, business and communication.', 9000, '#1f9d55'],
        ['gemstone', 'Yellow Sapphire (Pukhraj)', 'Jupiter', 'Ceylon yellow sapphire for Jupiter: wisdom, children, marriage and prosperity.', 15000, '#f2c230'],
        ['gemstone', 'Diamond (Heera)', 'Venus', 'Certified diamond for Venus: relationships, comfort, art and luxury.', 25000, '#d7ecff'],
        ['gemstone', 'Blue Sapphire (Neelam)', 'Saturn', 'Ceylon blue sapphire for Saturn. Strong in effect; tried only on Acharya Ji\'s advice.', 18000, '#1e4fb4'],
        ['gemstone', 'Hessonite (Gomed)', 'Rahu', 'Ceylon hessonite garnet for Rahu: clarity in confusion, career and foreign matters.', 3000, '#b5651d'],
        ['gemstone', "Cat's Eye (Lehsunia)", 'Ketu', "Chrysoberyl cat's eye for Ketu: intuition, spiritual growth and protection from sudden losses.", 6000, '#a3a35a'],
        ['gemstone', 'Opal', 'Venus (uparatna)', 'Australian opal, a gentler substitute for diamond for Venus.', 2500, '#9fd8d1'],
        ['gemstone', 'Citrine (Sunela)', 'Jupiter (uparatna)', 'Affordable substitute for yellow sapphire for Jupiter.', 1500, '#f0a830'],
        ['gemstone', 'Amethyst (Katela)', 'Saturn (uparatna)', 'Gentle substitute for blue sapphire for Saturn.', 1200, '#8e44ad'],
        ['gemstone', 'Peridot', 'Mercury (uparatna)', 'Light green stone, a substitute for emerald for Mercury.', 1200, '#9acd32'],
        ['gemstone', 'Moonstone', 'Moon (uparatna)', 'Soft substitute for pearl for the Moon.', 900, '#dfe6ef'],
        ['gemstone', 'Garnet (Tamra)', 'Sun (uparatna)', 'Deep red garnet, a substitute for ruby for the Sun.', 1100, '#8b1e2f'],
        ['rudraksha', 'One Mukhi Rudraksha', 'Sun', 'Rare one-faced rudraksha associated with Lord Shiva and focus.', 11000, '#8a4b25'],
        ['rudraksha', 'Five Mukhi Rudraksha', 'Jupiter', 'The most common rudraksha, for peace and daily jaap.', 501, '#8a4b25'],
        ['rudraksha', 'Seven Mukhi Rudraksha', 'Saturn', 'Associated with Goddess Lakshmi; worn for financial stability.', 1500, '#8a4b25'],
        ['rudraksha', 'Gauri Shankar Rudraksha', 'Moon', 'Two naturally joined beads, worn for harmony in relationships.', 3100, '#8a4b25'],
        ['yantra', 'Shri Yantra', 'Lakshmi', 'Energised copper Shri Yantra for the puja place, home or office.', 1100, '#d9a441'],
        ['yantra', 'Navgrah Yantra', 'Nine planets', 'Copper yantra of the nine planets for daily worship.', 951, '#d9a441'],
        ['yantra', 'Kuber Yantra', 'Kuber', 'Copper Kuber yantra for the cash box or shop.', 751, '#d9a441'],
        ['yantra', 'Vastu Dosh Nivaran Yantra', 'Vastu', 'Copper yantra used in traditional Vastu correction.', 1100, '#d9a441'],
        ['mala', 'Rudraksha Jaap Mala (108+1)', 'Jaap', 'Five mukhi rudraksha mala for mantra jaap.', 1100, '#8a4b25'],
        ['mala', 'Sphatik (Crystal) Mala', 'Moon / Venus', 'Clear quartz mala for Lakshmi and Shukra mantras.', 1100, '#e6f2ff'],
        ['mala', 'Tulsi Kanthi Mala', 'Vishnu', 'Tulsi bead mala for Vishnu and Krishna devotion.', 351, '#6b8e23'],
        ['kit', 'Navgrah Puja Samagri Kit', 'Puja', 'Complete samagri for a Navgrah puja at home, with vidhi booklet.', 1100, '#b4471b'],
        ['kit', 'Lakshmi Ganesh Puja Kit', 'Diwali', 'Samagri for Diwali Lakshmi Ganesh puja at home.', 1500, '#b4471b'],
        ['kit', 'Rudrabhishek Samagri Kit', 'Shiva', 'Samagri for a Rudrabhishek at home or a temple.', 1250, '#b4471b'],
        ['samagri', 'Pure Gangajal (500 ml)', 'Puja', 'Sealed Gangajal for daily puja and sankalp.', 151, '#3fa7d6'],
        ['samagri', 'Hawan Samagri (1 kg)', 'Hawan', 'Traditional mix of herbs and resins for hawan.', 351, '#8b5a2b'],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $i => [$category, $name, $for, $summary, $price, $colour]) {
            $slug = Str::slug($name);
            if (DB::table('products')->where('slug', $slug)->exists()) {
                continue;
            }
            $img = 'images/art/store-'.$slug.'.jpg';
            DB::table('products')->insert([
                'ref' => (string) Str::ulid(), 'slug' => $slug, 'category' => $category, 'name' => $name, 'summary' => $summary,
                'description' => $summary."\n\nAssociated with: $for. "
                    .($category === 'gemstone'
                        ? 'Acharya Ji recommends a stone only after studying your chart, and tells you the weight, metal, finger, day and mantra for wearing it. Each stone comes with its lab certificate.'
                        : 'Sent with simple instructions for use and the related mantra.'),
                'price_minor' => $price * 100, 'stock' => 0, 'max_per_order' => $category === 'gemstone' ? 1 : 5, 'active' => false, 'sort' => $i,
                'image_path' => is_file(public_path($img)) ? $img : null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
