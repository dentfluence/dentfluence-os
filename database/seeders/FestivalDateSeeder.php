<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FestivalDateSeeder extends Seeder
{
    /**
     * Seed 20+ global festival / awareness dates into mkt_festival_dates.
     * Safe to run multiple times — truncates first (dev-only seeder).
     */
    public function run(): void
    {
        DB::table('mkt_festival_dates')->truncate();

        $now = now();

        $dates = [

            // ----------------------------------------------------------------
            // DENTAL AWARENESS
            // ----------------------------------------------------------------
            [
                'name'                  => 'World Oral Health Day',
                'local_name'            => null,
                'category'              => 'dental',
                'month'                 => 3,
                'day'                   => 20,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Global awareness day for oral health. Great for educational content, offers on checkups.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#WorldOralHealthDay', '#OralHealth', '#DentalCare', '#HealthySmile']),
                'is_active'             => true,
            ],
            [
                'name'                  => "Dentist's Day",
                'local_name'            => null,
                'category'              => 'dental',
                'month'                 => 3,
                'day'                   => 6,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Celebrate your dental team and the profession.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#DentistsDay', '#DentalHeroes', '#ThankYourDentist']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Children\'s Dental Health Month',
                'local_name'            => null,
                'category'              => 'dental',
                'month'                 => 2,
                'day'                   => 1,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Month-long awareness. Great for a campaign targeting parents and kids.',
                'suggested_content_type'=> 'carousel',
                'suggested_hashtags'    => json_encode(['#ChildrensDentalHealth', '#KidsDentist', '#HealthyTeeth']),
                'is_active'             => true,
            ],
            [
                // 1st Friday of October — World Smile Day
                'name'                  => 'World Smile Day',
                'local_name'            => null,
                'category'              => 'dental',
                'month'                 => 10,
                'day'                   => null,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => 1,
                'day_of_week'           => 5, // Friday
                'description'           => 'First Friday of October. Smile-related content, before/after cases, offers.',
                'suggested_content_type'=> 'reel',
                'suggested_hashtags'    => json_encode(['#WorldSmileDay', '#SmileMore', '#DentalSmile']),
                'is_active'             => true,
            ],

            // ----------------------------------------------------------------
            // NATIONAL / HEALTH
            // ----------------------------------------------------------------
            [
                'name'                  => 'World Health Day',
                'local_name'            => null,
                'category'              => 'national',
                'month'                 => 4,
                'day'                   => 7,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'WHO global health awareness day. Tie dental health to overall wellbeing.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#WorldHealthDay', '#HealthForAll', '#DentalHealth']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'World Environment Day',
                'local_name'            => null,
                'category'              => 'national',
                'month'                 => 6,
                'day'                   => 5,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Highlight eco-friendly practices at your clinic.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#WorldEnvironmentDay', '#GreenClinic', '#EcoDentistry']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'International Yoga Day',
                'local_name'            => 'अंतर्राष्ट्रीय योग दिवस',
                'category'              => 'national',
                'month'                 => 6,
                'day'                   => 21,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Connect dental health to holistic wellness / stress and oral health.',
                'suggested_content_type'=> 'reel',
                'suggested_hashtags'    => json_encode(['#YogaDay', '#HolisticHealth', '#WellnessAndDental']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Independence Day',
                'local_name'            => 'स्वतंत्रता दिवस',
                'category'              => 'national',
                'month'                 => 8,
                'day'                   => 15,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Patriotic content. Special offers or "free India, free smiles" themed posts.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#IndependenceDay', '#JaiHind', '#SwatantrataDiwas']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Republic Day',
                'local_name'            => 'गणतंत्र दिवस',
                'category'              => 'national',
                'month'                 => 1,
                'day'                   => 26,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Patriotic content, special clinic offers.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#RepublicDay', '#GanatantraDiwas', '#JaiHind']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'New Year',
                'local_name'            => 'नया साल',
                'category'              => 'national',
                'month'                 => 1,
                'day'                   => 1,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => '"New Year, New Smile" campaign. Offers on whitening, aligners, checkups.',
                'suggested_content_type'=> 'reel',
                'suggested_hashtags'    => json_encode(['#NewYear', '#NewSmile', '#NewYearOffers', '#HappyNewYear']),
                'is_active'             => true,
            ],

            // ----------------------------------------------------------------
            // REGIONAL / CULTURAL
            // ----------------------------------------------------------------
            [
                'name'                  => "Valentine's Day",
                'local_name'            => null,
                'category'              => 'regional',
                'month'                 => 2,
                'day'                   => 14,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Smile gifting, teeth whitening offers. "Gift a smile to your loved one."',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#ValentinesDay', '#GiftASmile', '#TeethWhitening', '#LoveYourSmile']),
                'is_active'             => true,
            ],
            [
                // 2nd Sunday of May
                'name'                  => "Mother's Day",
                'local_name'            => null,
                'category'              => 'regional',
                'month'                 => 5,
                'day'                   => null,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => 2,
                'day_of_week'           => 0, // Sunday
                'description'           => 'Second Sunday of May. Offer family dental packages or gift vouchers.',
                'suggested_content_type'=> 'carousel',
                'suggested_hashtags'    => json_encode(['#MothersDay', '#SmileForMom', '#FamilyDentalCare']),
                'is_active'             => true,
            ],
            [
                // 3rd Sunday of June
                'name'                  => "Father's Day",
                'local_name'            => null,
                'category'              => 'regional',
                'month'                 => 6,
                'day'                   => null,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => 3,
                'day_of_week'           => 0, // Sunday
                'description'           => 'Third Sunday of June. Implants, crowns — "Give Dad the smile he deserves."',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#FathersDay', '#SmileForDad', '#DentalImplants']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Christmas',
                'local_name'            => null,
                'category'              => 'regional',
                'month'                 => 12,
                'day'                   => 25,
                'festival_date'         => null,
                'is_recurring'          => true,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Festive seasonal content, year-end offers.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#Christmas', '#MerryChristmas', '#HolidaySmile']),
                'is_active'             => true,
            ],

            // ----------------------------------------------------------------
            // RELIGIOUS (floating — use approximate 2026 dates as festival_date)
            // ----------------------------------------------------------------
            [
                'name'                  => 'Diwali',
                'local_name'            => 'दीवाली',
                'category'              => 'religious',
                'month'                 => null,
                'day'                   => null,
                'festival_date'         => '2026-10-19', // approx 2026
                'is_recurring'          => false,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Festival of lights. Special Diwali dental offers, smile makeover packages.',
                'suggested_content_type'=> 'reel',
                'suggested_hashtags'    => json_encode(['#Diwali', '#DiwaliSmile', '#HappyDiwali', '#DentalOffer']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Holi',
                'local_name'            => 'होली',
                'category'              => 'religious',
                'month'                 => null,
                'day'                   => null,
                'festival_date'         => '2026-03-13', // approx 2026
                'is_recurring'          => false,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Festival of colors. "Brighten your smile like the colors of Holi."',
                'suggested_content_type'=> 'reel',
                'suggested_hashtags'    => json_encode(['#Holi', '#HappyHoli', '#BrightenYourSmile', '#WhiteningSpecial']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Navratri',
                'local_name'            => 'नवरात्रि',
                'category'              => 'religious',
                'month'                 => null,
                'day'                   => null,
                'festival_date'         => '2026-10-02', // approx 2026
                'is_recurring'          => false,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Festive season engagement. Smile-related motivational content.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#Navratri', '#HappyNavratri', '#SmileFestival']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Dussehra',
                'local_name'            => 'दशहरा',
                'category'              => 'religious',
                'month'                 => null,
                'day'                   => null,
                'festival_date'         => '2026-10-11', // approx 2026
                'is_recurring'          => false,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => '"Victory over dental decay." Educational + motivational content.',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#Dussehra', '#HappyDussehra', '#HealthySmile']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Eid ul-Fitr',
                'local_name'            => 'ईद',
                'category'              => 'religious',
                'month'                 => null,
                'day'                   => null,
                'festival_date'         => '2026-03-20', // approx 2026
                'is_recurring'          => false,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => 'Eid greetings. "Share your brightest smile this Eid."',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#EidMubarak', '#Eid', '#SmileOnEid']),
                'is_active'             => true,
            ],
            [
                'name'                  => 'Ganesh Chaturthi',
                'local_name'            => 'गणेश चतुर्थी',
                'category'              => 'religious',
                'month'                 => null,
                'day'                   => null,
                'festival_date'         => '2026-08-23', // approx 2026
                'is_recurring'          => false,
                'nth_week'              => null,
                'day_of_week'           => null,
                'description'           => '"Ganpati Bappa Morya! Smile bright this festive season."',
                'suggested_content_type'=> 'post',
                'suggested_hashtags'    => json_encode(['#GaneshChaturthi', '#GanpatiBappaMorya', '#FestiveSmile']),
                'is_active'             => true,
            ],
        ];

        // Add timestamps to each row
        $rows = array_map(fn ($d) => array_merge($d, [
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $dates);

        DB::table('mkt_festival_dates')->insert($rows);

        $this->command->info('FestivalDateSeeder: ' . count($rows) . ' dates inserted.');
    }
}
