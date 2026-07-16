<?php

namespace Database\Seeders;

use App\Models\KbBlock;
use App\Models\KbTopic;
use Illuminate\Database\Seeder;

/**
 * KnowledgeBankSeeder — Case Acceptance Engine, Milestone 3.
 * See docs/plan-case-acceptance-engine.md §5.1 / §11 and
 *     docs/plan-case-acceptance-engine-implementation.md (Milestone 3).
 *
 * Seeds the FIRST scenario: Missing Tooth → Implant / Bridge / Denture →
 * crown material. Global education only — NO prices, brands, clinic or patient
 * data (the model-layer guard enforces this). Media is added in Milestone 4.
 *
 * IDEMPOTENT: topics keyed on a fixed `content_uuid` + `slug`; blocks keyed on
 * (topic, block_type, title). Re-running updates in place, never duplicates.
 *
 * ⚠️ CLINICAL CONTENT IS A FIRST DRAFT — patient-friendly starter text meant
 * for the treating dentist to review and refine for accuracy and clinic voice.
 * `depth` is `standard` for every block (V1 authors one depth); `{{tokens}}`
 * are resolved at render (whitelist: tooth_name, patient_first_name, tooth_count).
 *
 * Run: php artisan db:seed --class=KnowledgeBankSeeder
 */
class KnowledgeBankSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->topics() as $topic) {
            $model = KbTopic::updateOrCreate(
                ['content_uuid' => $topic['content_uuid']],
                [
                    'slug'         => $topic['slug'],
                    'type'         => $topic['type'],
                    'title'        => $topic['title'],
                    'version'      => '1.0.0',
                    'status'       => 'published',
                    'published_at' => now(),
                ]
            );

            foreach ($topic['blocks'] as $i => $block) {
                KbBlock::updateOrCreate(
                    [
                        'kb_topic_id' => $model->id,
                        'block_type'  => $block['block_type'],
                        'title'       => $block['title'],
                    ],
                    [
                        'body'       => $block['body'],
                        'depth'      => 'standard',
                        'locale'     => 'en',
                        'sort_order' => $i,
                        'version'    => '1.0.0',
                    ]
                );
            }
        }
    }

    /**
     * The Missing Tooth knowledge set. Fixed content_uuids keep re-seeds and a
     * future central content-sync API stable.
     */
    private function topics(): array
    {
        return [
            // ── Condition ────────────────────────────────────────────────
            [
                'content_uuid' => '0f3c8a10-0001-4a00-9000-0000000000c1',
                'slug'         => 'missing-tooth',
                'type'         => 'condition',
                'title'        => 'Missing Tooth',
                'blocks'       => [
                    ['block_type' => 'intro', 'title' => 'What a missing tooth means',
                     'body' => "Hi {{patient_first_name}}, losing tooth {{tooth_name}} is common and very treatable. The gap can be replaced so your smile, chewing, and bite feel natural again. The right choice depends on your bone, your neighbouring teeth, and what matters most to you."],
                    ['block_type' => 'risk', 'title' => 'What happens if the gap is left',
                     'body' => "A missing tooth is not only about the gap. Over time the jawbone in that area slowly shrinks, the neighbouring teeth can tilt into the space, and the opposing tooth may over-erupt. This can change your bite and make treatment harder later, which is why replacing the tooth sooner usually gives the best result."],
                    ['block_type' => 'comparison', 'title' => 'Your three main options',
                     'body' => "There are three well-proven ways to replace a missing tooth: a dental implant (a titanium root with a crown), a bridge (crowns on the neighbouring teeth carrying a false tooth between them), and a removable partial denture (a custom clip-in tooth). Each differs in cost, time, how it protects your other teeth, and how it feels day to day. Your dentist will walk you through which suits tooth {{tooth_name}} best."],
                    ['block_type' => 'faq', 'title' => 'Do I have to replace it right away?',
                     'body' => "Not the same day, but sooner is generally better. Waiting allows bone loss and tooth drifting that can limit your options and add cost later. Your dentist will tell you if there is any urgency in your specific case."],
                ],
            ],

            // ── Procedure: Implant ───────────────────────────────────────
            [
                'content_uuid' => '0f3c8a10-0002-4a00-9000-0000000000c2',
                'slug'         => 'dental-implant',
                'type'         => 'procedure',
                'title'        => 'Dental Implant',
                'blocks'       => [
                    ['block_type' => 'intro', 'title' => 'What a dental implant is',
                     'body' => "A dental implant is a small titanium post that acts as a new tooth root. It is placed into the jawbone where tooth {{tooth_name}} used to be, and once it has bonded to the bone a natural-looking crown is fixed on top. It is the closest thing to replacing the whole tooth, root and all."],
                    ['block_type' => 'advantage', 'title' => 'Why patients choose an implant',
                     'body' => "An implant stands on its own, so your healthy neighbouring teeth are not touched or trimmed. It helps preserve the jawbone, is easy to clean like a natural tooth, and with good care can last for decades. It also feels and functions the most like a real tooth."],
                    ['block_type' => 'disadvantage', 'title' => 'Things to consider',
                     'body' => "An implant is usually the higher upfront investment and takes longer overall, because the bone needs time to bond with the post. It is a minor surgical procedure and needs enough healthy bone; if bone is thin, a small graft may be advised first."],
                    ['block_type' => 'risk', 'title' => 'Risks and how we manage them',
                     'body' => "Implants have a very high long-term success rate, but as with any procedure there are small risks such as infection or slow healing, and success depends on good oral hygiene and not smoking. Your dentist screens your health and bone beforehand to keep these risks low."],
                    ['block_type' => 'healing_timeline', 'title' => 'How long it takes',
                     'body' => "After placement, the implant typically integrates with the bone over about 2 to 4 months. Some cases allow a temporary tooth in the meantime. Once healed, the final crown is fitted in one or two short visits. Your dentist will give you a timeline for your case."],
                    ['block_type' => 'maintenance', 'title' => 'Looking after your implant',
                     'body' => "Care is simple: brush twice a day, clean around the implant with floss or an interdental brush, and keep your regular check-ups. Treated like a natural tooth, an implant can serve you for many years."],
                    ['block_type' => 'faq', 'title' => 'Is the procedure painful?',
                     'body' => "Placement is done under local anaesthesia, so you should feel pressure but not pain during the procedure. Most patients compare the recovery to a simple extraction and manage well with routine pain relief."],
                ],
            ],

            // ── Procedure: Bridge ────────────────────────────────────────
            [
                'content_uuid' => '0f3c8a10-0003-4a00-9000-0000000000c3',
                'slug'         => 'dental-bridge',
                'type'         => 'procedure',
                'title'        => 'Dental Bridge',
                'blocks'       => [
                    ['block_type' => 'intro', 'title' => 'What a dental bridge is',
                     'body' => "A bridge fills the gap at tooth {{tooth_name}} by placing crowns on the two neighbouring teeth and joining a false tooth between them. It is fixed in place, so it does not come out, and it can be completed in a shorter time than an implant."],
                    ['block_type' => 'advantage', 'title' => 'Why patients choose a bridge',
                     'body' => "A bridge is fixed and stable, looks natural, and is usually faster and lower in upfront cost than an implant. It is a good option when the neighbouring teeth already have large fillings or would benefit from crowns anyway."],
                    ['block_type' => 'disadvantage', 'title' => 'Things to consider',
                     'body' => "To hold the bridge, the two neighbouring teeth must be trimmed down for crowns, even if they are healthy. A bridge also does not stop bone loss under the gap, and cleaning beneath the false tooth needs a little extra care."],
                    ['block_type' => 'risk', 'title' => 'Risks and how we manage them',
                     'body' => "The main long-term consideration is that the supporting teeth carry extra load and must stay healthy; decay or gum problems under a crown can affect the whole bridge. Good cleaning and regular check-ups keep it lasting well."],
                    ['block_type' => 'healing_timeline', 'title' => 'How long it takes',
                     'body' => "A bridge is usually completed in about two to three visits over a couple of weeks: preparing the teeth and taking a mould, then fitting the finished bridge once the lab has made it. A temporary bridge protects the teeth in between."],
                    ['block_type' => 'maintenance', 'title' => 'Looking after your bridge',
                     'body' => "Brush normally and use a floss threader or interdental brush to clean under the false tooth daily. With good hygiene and regular reviews, a well-made bridge lasts many years."],
                    ['block_type' => 'faq', 'title' => 'Will it look natural?',
                     'body' => "Yes. Modern bridges are colour-matched to your own teeth and, especially with tooth-coloured materials, are very hard to tell apart from natural teeth."],
                ],
            ],

            // ── Procedure: Removable Partial Denture ─────────────────────
            [
                'content_uuid' => '0f3c8a10-0004-4a00-9000-0000000000c4',
                'slug'         => 'removable-partial-denture',
                'type'         => 'procedure',
                'title'        => 'Removable Partial Denture',
                'blocks'       => [
                    ['block_type' => 'intro', 'title' => 'What a removable denture is',
                     'body' => "A removable partial denture is a custom-made tooth (or teeth) on a small gum-coloured base that clips onto your natural teeth. It fills the gap at tooth {{tooth_name}} and can be taken out for cleaning. It is the most economical way to replace a missing tooth."],
                    ['block_type' => 'advantage', 'title' => 'Why patients choose a denture',
                     'body' => "A denture is the lowest-cost option, is non-surgical, and can replace one or several teeth at once. It does not require trimming healthy teeth, and it can be made quite quickly."],
                    ['block_type' => 'disadvantage', 'title' => 'Things to consider',
                     'body' => "Because it is removable, a denture is less stable than an implant or bridge and can take time to get used to. It needs to be taken out to clean, may need adjusting over time as the gums change, and does not prevent bone loss under the gap."],
                    ['block_type' => 'risk', 'title' => 'Comfort and fit',
                     'body' => "Early on a denture can feel bulky and may affect speech or eating for a short while; most people adapt within a couple of weeks. Clasps rest on natural teeth, so keeping those teeth and gums clean is important to avoid decay around them."],
                    ['block_type' => 'maintenance', 'title' => 'Looking after your denture',
                     'body' => "Clean the denture daily over water or a soft towel, remove it at night to rest your gums, and keep your natural teeth brushed as usual. Bring it to your check-ups so the fit can be reviewed."],
                    ['block_type' => 'faq', 'title' => 'Can I eat normally with it?',
                     'body' => "You can eat most foods, though it helps to start with softer foods and smaller bites while you adjust. Chewing on both sides makes the denture more stable."],
                ],
            ],

            // ── Materials: Crown options (shown after a procedure is chosen)
            [
                'content_uuid' => '0f3c8a10-0005-4a00-9000-0000000000c5',
                'slug'         => 'crown-zirconia',
                'type'         => 'material',
                'title'        => 'Zirconia Crown',
                'blocks'       => [
                    ['block_type' => 'intro', 'title' => 'About zirconia crowns',
                     'body' => "Zirconia is a strong, tooth-coloured ceramic used for the visible crown. It is metal-free and blends well with natural teeth, making it a popular all-round choice for both front and back teeth."],
                    ['block_type' => 'advantage', 'title' => 'Strengths',
                     'body' => "Zirconia is very strong and durable, resists chipping, and looks natural with no dark metal line at the gum. It is well tolerated and a reliable long-term material, especially for back teeth that take heavy chewing forces."],
                    ['block_type' => 'disadvantage', 'title' => 'Trade-offs',
                     'body' => "Zirconia usually costs more than a metal-based crown. For very front teeth, the most translucent aesthetic materials can occasionally look even more lifelike, though modern zirconia is excellent in most cases."],
                ],
            ],
            [
                'content_uuid' => '0f3c8a10-0006-4a00-9000-0000000000c6',
                'slug'         => 'crown-pfm',
                'type'         => 'material',
                'title'        => 'Porcelain-Fused-to-Metal Crown',
                'blocks'       => [
                    ['block_type' => 'intro', 'title' => 'About PFM crowns',
                     'body' => "A porcelain-fused-to-metal (PFM) crown has a metal core for strength with a tooth-coloured porcelain outer layer. It has been used reliably for decades and balances durability with a natural appearance."],
                    ['block_type' => 'advantage', 'title' => 'Strengths',
                     'body' => "PFM crowns are strong, time-tested, and usually more economical than all-ceramic options, making them a dependable choice for many situations, particularly back teeth."],
                    ['block_type' => 'disadvantage', 'title' => 'Trade-offs',
                     'body' => "Because of the metal core, a thin dark line can sometimes show at the gum over time, and PFM is slightly less translucent than all-ceramic crowns like zirconia or E-max. This matters most on very visible front teeth."],
                ],
            ],
            [
                'content_uuid' => '0f3c8a10-0007-4a00-9000-0000000000c7',
                'slug'         => 'crown-emax',
                'type'         => 'material',
                'title'        => 'E-max (Lithium Disilicate) Crown',
                'blocks'       => [
                    ['block_type' => 'intro', 'title' => 'About E-max crowns',
                     'body' => "E-max is a premium all-ceramic material (lithium disilicate) prized for its lifelike, translucent appearance. It is metal-free and is often chosen for front teeth where matching the natural look closely matters most."],
                    ['block_type' => 'advantage', 'title' => 'Strengths',
                     'body' => "E-max offers outstanding aesthetics with excellent translucency, so it mimics natural enamel beautifully, while still being strong enough for everyday use. There is no metal, so no dark gum line."],
                    ['block_type' => 'disadvantage', 'title' => 'Trade-offs',
                     'body' => "E-max is typically among the higher-cost crown options. For teeth that take very heavy biting forces, zirconia may be recommended for its extra strength; your dentist will advise what suits tooth {{tooth_name}}."],
                ],
            ],
        ];
    }
}
