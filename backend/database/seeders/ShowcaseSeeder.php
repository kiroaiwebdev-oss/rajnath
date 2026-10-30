<?php

namespace Database\Seeders;

use App\Domain\Showcase\Profile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starting content for Admin › About & awards and Admin › Gallery, taken from the confirmed
 * biography (owner, 28 Oct 2026). Runs only while the tables are empty, so the owner's edits are
 * never overwritten by a later deploy.
 */
class ShowcaseSeeder extends Seeder
{
    /** [section, title, detail, year, group, featured, image] */
    private const ENTRIES = [
        ['award', 'Professor Award 2026', 'IVAF International Vedic Astrology Federation, for participation in the Sri Lanka Sacred Astrological Symposium (Astrology, Vastu & Gemstone), Kandy, 8 Sep 2026', '2026', 'international', true, null],
        ['award', 'Global Astrological Iconic Award & Lifetime Achievement Honour', 'Subramanyam Astrological & Spiritual Convocation, Malaysia', '2025', 'international', true, 'images/owner/award-malaysia-2025-480.jpg'],
        ['award', 'US Global Excellence Award', 'New Delhi', '2024', 'international', false, null],
        ['award', 'Maharshi Gyan Ratan Award', 'Vietnam', '2024', 'international', false, null],
        ['award', 'Jyotish Siromani International Award', 'Chennai', '2024', 'international', false, null],
        ['award', 'Hind Gaurav Award', 'Indore', '2024', 'international', false, null],
        ['award', 'Jyotish Samridhi Award', 'Tamil Nadu', '2024', 'national', false, null],
        ['award', 'Mithila Ratna Award', 'Madhubani', '2024', 'national', false, null],
        ['award', 'Jyotish Gyan Ratna Award', 'Tirupati', '2023', 'national', false, null],
        ['award', 'Jyotish Chakravarthy Award', 'Mumbai', '2023', 'national', false, null],
        ['award', 'Golden Achievement Award', '28th International Conference, Kolkata', '2005', 'national', false, null],
        ['award', 'Vishwabandhu Award', '27th International Conference, Kolkata', '2004', 'national', false, null],
        ['award', 'Jyotish Shri Award', '44th National Astrology Seminar, Madhubani', '2002', 'national', false, null],
        ['award', 'Gold Medal, Acharya Pariksha (Ganit Jyotish)', 'K.S.D.S.U., Darbhanga; also 1st position, Shalaka Pariksha (1998)', '1999', 'national', false, null],
        ['education', 'Ph.D. in Astrology', 'K.S.D.S.U., Darbhanga — 1st Division. Thesis: "Varahmihiracharyakrit Mayurchitraka: Pathlochan Purvak Samikshatmak Adhyayan"', '2014', null, true, null],
        ['education', 'Acharya (Phalit Jyotish)', 'K.S.D.S.U., Darbhanga — 1st Division', '2003', null, false, null],
        ['education', 'Acharya (Ganit Jyotish), Gold Medalist', 'K.S.D.S.U., Darbhanga — 1st Division', '1999', null, false, null],
        ['education', 'Shastri (Hons.)', 'K.S.D.S.U., Darbhanga', '1996', null, false, null],
        ['education', 'Upashastri', 'K.S.D.S.U., Darbhanga — 1st Division', '1993', null, false, null],
        ['education', 'Madhyama', 'B.S.S.B., Patna — 1st Division', '1991', null, false, null],
        ['position', 'Director — Jyotirveda Vigyan Sansthan', 'Research and education in Vedic sciences: agriculture, disaster prediction and health.', '2011 – present', null, false, null],
        ['position', 'Founder & Director — Sanatan Vedic Gurukul, Madhubani', null, 'April 2024 – present', null, false, null],
        ['position', 'International Chief Consultant — International Astrology Federation (USA)', null, null, null, false, null],
        ['position', 'Vice Chairman — World Climate Change Foundation, Bihar', null, null, null, false, null],
        ['position', 'Jyotish & Karmkand consultant — Jyotish Mandap, Mahavir Mandir Trust, Patna', '17 years of service.', '2002 – 2016', null, true, null],
        ['position', 'Faculty — Vinodanand Research Center, Patna', null, '2004', null, false, null],
        ['expertise', 'Vedic astrology (Jyotish)', null, null, null, false, null],
        ['expertise', 'Kundali analysis', null, null, null, false, null],
        ['expertise', 'Kundali matching', null, null, null, false, null],
        ['expertise', 'Vastu Shastra', null, null, null, false, null],
        ['expertise', 'Numerology', null, null, null, false, null],
        ['expertise', 'Palmistry', null, null, null, false, null],
        ['expertise', 'Muhurat', null, null, null, false, null],
        ['expertise', 'Gemstone recommendation', null, null, null, false, null],
        ['expertise', 'Karmkand and puja', null, null, null, false, null],
        ['research', 'Jyotish Shastra ke Drishti se Bhukamp ka Purvanuman', 'Rashtriya Sanskrit Sansthan, Vedavyas Campus', '2020', null, false, null],
        ['research', 'Kurma Chakra and Bhukamp research', 'B.H.U., Varanasi', '2015', null, false, null],
        ['research', 'Bhukamp ke Purvanuman ka Jyotishshastriya Anusandhan', 'K.S.D.S.U., Darbhanga', '2016', null, false, null],
        ['research', 'Papers at B.H.U. and the Oriental Conference, Tirupati', null, '2007 – 2017', null, false, null],
        ['book', 'Mayurchitraka Mimamsa — Varahamihira', null, null, null, false, null],
        ['book', 'Jyotirved Vigyan', null, null, null, false, null],
        ['book', 'Jyotish Drishtya Mansik Rog Vimarsh', null, null, null, false, null],
        ['book', 'Jyotish Shastra ke Drishti se Netra Rog Vimarsh', null, null, null, false, null],
        ['book', 'Prakritik Aapda aur Jyotish Shastra', null, null, null, false, null],
        ['book', 'Cancer Rog ka Jyotishshastriya Adhyayan', null, null, null, false, null],
        ['book', 'Ved Manjari, Nitya Karma Ratna Mala & Sanskrit Praveshika', null, null, null, false, null],
        ['media', 'Dainik Bhaskar', 'Columnist', '2014 – present', null, false, null],
        ['media', 'Dainik Jagran', 'Columnist', null, null, false, null],
        ['media', 'Hindustan', 'Weekly column', '2006 – 2010', null, false, null],
        ['media', 'Radio Mirchi 98.3 FM & Radio City', '"Kaal Dhamal"', null, null, false, null],
        ['media', 'ETV Patna and Maurya TV', 'Daily programmes', '2010 – 2011', null, false, null],
        ['media', 'News18 and Kashish News', null, null, null, false, null],
        ['media', 'Jyotishprabha Traimasik Patrika, Patna', 'Editor', null, null, false, null],
    ];

    /** [title, category, description, [photo paths under public/images/owner]] */
    private const ALBUMS = [
        ['Awards and honours', 'awards', 'Trophies, certificates and honours received in India and abroad.', ['award-malaysia-2025-480.jpg', 'awards-wall-wide-1200.jpg']],
        ['At the office in Kankarbagh, Patna', 'office', 'Where Acharya Ji meets visitors for consultations.', ['office-wide-960.jpg', 'desk-black-480.jpg', 'writing-navy-480.jpg', 'writing-red-480.jpg', 'seated-navy-480.jpg']],
    ];

    public function run(): void
    {
        if (! DB::table('profile_entries')->exists()) {
            $sort = [];
            foreach (self::ENTRIES as [$section, $title, $detail, $year, $group, $featured, $image]) {
                $sort[$section] = ($sort[$section] ?? 0) + 1;
                DB::table('profile_entries')->insert(['section' => $section, 'title' => $title, 'detail' => $detail, 'year' => $year, 'group' => $group,
                    'featured' => $featured, 'image_path' => $image, 'published' => true, 'sort' => $sort[$section] * 10, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if (! DB::table('gallery_albums')->exists()) {
            foreach (self::ALBUMS as $n => [$title, $category, $description, $photos]) {
                $id = DB::table('gallery_albums')->insertGetId(['ref' => (string) Str::ulid(), 'slug' => Str::slug($title), 'title' => $title, 'description' => $description,
                    'category' => $category, 'location' => 'Patna', 'published' => true, 'featured' => true, 'sort' => ($n + 1) * 10, 'published_at' => now(),
                    'created_at' => now(), 'updated_at' => now()]);
                foreach ($photos as $i => $file) {
                    $path = 'images/owner/'.$file;
                    [$w, $h] = is_file(public_path($path)) ? (getimagesize(public_path($path)) ?: [null, null]) : [null, null];
                    DB::table('gallery_items')->insert(['album_id' => $id, 'kind' => 'photo', 'path' => $path, 'thumb_path' => $path, 'width' => $w, 'height' => $h,
                        'sort' => ($i + 1) * 10, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        Profile::forget();
    }
}
