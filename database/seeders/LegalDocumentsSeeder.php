<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Seeds the initial privacy policy and terms of use in both languages.
 *
 * Safe to re-run: an existing document is left untouched so dashboard edits
 * survive. The English texts are translations of the Arabic originals — the
 * Arabic remains the authoritative version.
 */
class LegalDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::defaults() as $key => $document) {
            LegalDocument::firstOrCreate(['key' => $key], $document);
        }
    }

    /** @return array<string, array{title_ar: string, title_en: string, content_ar: string, content_en: string}> */
    private static function defaults(): array
    {
        return [
            'privacy' => [
                'title_ar' => 'سياسة الخصوصية',
                'title_en' => 'Privacy Policy',
                'content_ar' => <<<'HTML'
<h2>مقدمة</h2>
<p>توضح هذه السياسة كيف تجمع منصة «صكوكي» (الموقع الإلكتروني وتطبيق الجوال) بياناتك وكيف تستخدمها وتحميها. باستخدامك المنصة فأنت توافق على ما ورد في هذه السياسة.</p>
<h2>البيانات التي نجمعها</h2>
<ul>
<li><strong>بيانات الهوية:</strong> الاسم ورقم الهوية الوطنية ورقم الجوال، وتأتي من السجلات الرسمية للملكية ولا يمكن تعديلها من داخل المنصة.</li>
<li><strong>بيانات التواصل الاختيارية:</strong> البريد الإلكتروني ورقم الواتساب إن اخترت إضافتها.</li>
<li><strong>بيانات الأملاك:</strong> قطع الأراضي والصكوك والقرارات المساحية والمستندات المرتبطة بملكيتك.</li>
<li><strong>بيانات تقنية:</strong> رمز جلسة الدخول، وسجل تحميل المستندات لأغراض التدقيق.</li>
</ul>
<h2>كيف نستخدم بياناتك</h2>
<ul>
<li>التحقق من هويتك عند تسجيل الدخول برمز التحقق المؤقت (OTP).</li>
<li>عرض أملاكك وصكوكك ومستنداتك — ولا يطّلع أي مالك آخر عليها.</li>
<li>معالجة طلبات تعديل البيانات التي ترفعها.</li>
<li>تحسين الخدمة ومعالجة الأعطال.</li>
</ul>
<h2>حماية البيانات</h2>
<p>تُنقل جميع البيانات مشفّرة عبر HTTPS. في تطبيق الجوال يُحفظ رمز الجلسة في التخزين الآمن المشفّر للنظام، ولا تُحفظ كلمات مرور. كل عملية تحميل مستند تُسجّل في سجل تدقيق.</p>
<h2>الأطراف الثالثة</h2>
<p>نستخدم خدمة خرائط (Mapbox / OpenStreetMap) لعرض مواقع القطع؛ تُرسل لها إحداثيات الخريطة المعروضة فقط ولا تُشارك معها أي بيانات شخصية. لا نبيع بياناتك ولا نشاركها مع أي جهة تجارية، ولا نفصح عنها إلا للجهات الرسمية المختصة وفق الأنظمة.</p>
<h2>الاحتفاظ بالبيانات وحقوقك</h2>
<p>نحتفظ ببيانات الملكية ما دامت قائمة في السجلات الرسمية. يمكنك تعديل بيانات تواصلك من التطبيق، وطلب تصحيح بيانات أملاكك عبر «طلبات التعديل»، وطلب إيقاف حسابك بالتواصل معنا.</p>
<h2>التعديلات على هذه السياسة</h2>
<p>قد نحدّث هذه السياسة من وقت لآخر، وسيُشار إلى تاريخ آخر تحديث أعلى الصفحة. استمرارك في استخدام المنصة بعد التحديث يعني موافقتك عليه.</p>
<h2>التواصل</h2>
<p>لأي استفسار حول الخصوصية تواصل معنا عبر قنوات الدعم الموضحة في المنصة.</p>
HTML,
                'content_en' => <<<'HTML'
<h2>Introduction</h2>
<p>This policy explains how the Sukooki platform (the website and the mobile app) collects, uses and protects your data. By using the platform you agree to what is set out here.</p>
<h2>Data we collect</h2>
<ul>
<li><strong>Identity data:</strong> name, national ID and mobile number. These come from the official ownership records and cannot be edited inside the platform.</li>
<li><strong>Optional contact data:</strong> email address and WhatsApp number, if you choose to add them.</li>
<li><strong>Property data:</strong> parcels, deeds, survey decisions and the documents attached to your ownership.</li>
<li><strong>Technical data:</strong> your sign-in session token, and a record of document downloads kept for auditing.</li>
</ul>
<h2>How we use your data</h2>
<ul>
<li>To verify your identity at sign-in using a one-time code (OTP).</li>
<li>To show your properties, deeds and documents — no other owner can see them.</li>
<li>To process the data-correction requests you submit.</li>
<li>To improve the service and diagnose faults.</li>
</ul>
<h2>Protecting your data</h2>
<p>All data is transmitted encrypted over HTTPS. In the mobile app the session token is held in the operating system's encrypted secure storage, and no passwords are stored. Every document download is written to an audit log.</p>
<h2>Third parties</h2>
<p>We use a mapping service (Mapbox / OpenStreetMap) to display parcel locations; only the coordinates of the visible map are sent to it, and no personal data is shared. We do not sell your data or share it with any commercial party, and we disclose it only to the competent official authorities as required by law.</p>
<h2>Retention and your rights</h2>
<p>We retain ownership data for as long as it stands in the official records. You may update your contact details from the app, request a correction to your property data through "modification requests", and request that your account be suspended by contacting us.</p>
<h2>Changes to this policy</h2>
<p>We may update this policy from time to time, and the date of the latest update is shown at the top of the page. Continuing to use the platform after an update means you accept it.</p>
<h2>Contact</h2>
<p>For any privacy question, reach us through the support channels shown in the platform.</p>
HTML,
            ],
            'terms' => [
                'title_ar' => 'شروط الاستخدام',
                'title_en' => 'Terms of Use',
                'content_ar' => <<<'HTML'
<h2>القبول بالشروط</h2>
<p>باستخدامك منصة «صكوكي» (الموقع الإلكتروني وتطبيق الجوال) فأنت تقرّ بقراءة هذه الشروط والموافقة عليها. إن لم توافق على أي منها فيلزمك التوقف عن استخدام المنصة.</p>
<h2>وصف الخدمة</h2>
<p>تتيح المنصة لملّاك الأراضي الاطلاع على أملاكهم وصكوكهم وقراراتهم المساحية ومستنداتهم، ورفع طلبات تعديل البيانات. كل مستخدم يرى أملاكه المسجلة باسمه فقط.</p>
<h2>الحساب وتسجيل الدخول</h2>
<p>يتم الدخول برقم الجوال المسجّل في سجلات الملكية ورمز تحقق مؤقت. أنت مسؤول عن حماية جوالك وعدم مشاركة رموز التحقق مع أي طرف، وعن جميع الأنشطة التي تتم عبر جلستك.</p>
<h2>طبيعة البيانات المعروضة</h2>
<p>البيانات المعروضة في المنصة لأغراض الاطلاع والاسترشاد، ولا تُعد بديلاً عن الوثائق الرسمية الصادرة عن الجهات المختصة. عند أي تعارض تكون الحجية للسجلات والوثائق الرسمية.</p>
<h2>الاستخدام المقبول</h2>
<ul>
<li>يُمنع محاولة الوصول إلى بيانات مالك آخر أو أي جزء غير مصرّح لك به.</li>
<li>يُمنع إساءة استخدام المنصة أو تعطيلها أو تجاوز قيود الاستخدام.</li>
<li>يُمنع استخدام البيانات أو المستندات في أغراض احتيالية أو مخالفة للأنظمة.</li>
</ul>
<h2>الملكية الفكرية</h2>
<p>المنصة وتصميمها وشعارها ومحتواها التقني ملك لـ«صكوكي». لا يجوز نسخها أو إعادة استخدامها دون إذن كتابي مسبق.</p>
<h2>توفر الخدمة والمسؤولية</h2>
<p>نسعى لإتاحة الخدمة على مدار الساعة دون أن نضمن خلوّها من الانقطاع أو الخطأ. لا تتحمل المنصة أي مسؤولية عن أضرار ناتجة عن الاعتماد على البيانات المعروضة دون الرجوع للوثائق الرسمية.</p>
<h2>تعديل الشروط والنظام المطبق</h2>
<p>يحق لنا تعديل هذه الشروط في أي وقت مع الإشارة إلى تاريخ آخر تحديث. تخضع هذه الشروط لأنظمة المملكة العربية السعودية، وتختص جهاتها القضائية بأي نزاع ينشأ عنها.</p>
HTML,
                'content_en' => <<<'HTML'
<h2>Acceptance of these terms</h2>
<p>By using the Sukooki platform (the website and the mobile app) you confirm that you have read these terms and agree to them. If you do not agree to any part of them, you must stop using the platform.</p>
<h2>What the service does</h2>
<p>The platform lets landowners view their properties, deeds, survey decisions and documents, and submit requests to correct their data. Each user sees only the properties registered in their own name.</p>
<h2>Your account and signing in</h2>
<p>You sign in with the mobile number held in the ownership records and a one-time code. You are responsible for keeping your phone secure, for not sharing verification codes with anyone, and for all activity carried out through your session.</p>
<h2>The nature of the data shown</h2>
<p>The data shown on the platform is for reference and guidance. It does not replace the official documents issued by the competent authorities, and in the event of any conflict the official records and documents prevail.</p>
<h2>Acceptable use</h2>
<ul>
<li>Attempting to reach another owner's data, or any part of the platform you are not authorised to access, is prohibited.</li>
<li>Misusing or disrupting the platform, or circumventing usage limits, is prohibited.</li>
<li>Using the data or documents for fraudulent or otherwise unlawful purposes is prohibited.</li>
</ul>
<h2>Intellectual property</h2>
<p>The platform, its design, its logo and its technical content belong to Sukooki. They may not be copied or reused without prior written permission.</p>
<h2>Availability and liability</h2>
<p>We aim to keep the service available at all times, without warranting that it will be free of interruption or error. The platform accepts no liability for loss arising from reliance on the data shown without reference to the official documents.</p>
<h2>Changes to these terms and governing law</h2>
<p>We may amend these terms at any time, noting the date of the latest update. These terms are governed by the laws of the Kingdom of Saudi Arabia, and its courts have jurisdiction over any dispute arising from them.</p>
HTML,
            ],
        ];
    }
}
