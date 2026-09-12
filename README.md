# سنتر دريم

نظام Laravel عربي لإدارة مركز تعليمي: الطلاب، الاشتراكات، التحصيل، الخصومات، ردّ المبالغ، المواد، المدرسون، صرف المستحقات والتقارير المالية.

## المزايا

- واجهة RTL متجاوبة للمدير والسكرتير والمدرس، مع صلاحيات فعلية لكل دور.
- ملف مالي موحّد للطالب: المواد، الرسوم، الخصومات، الدفعات، الرصيد، الإلغاء وردّ المبالغ.
- تحصيل من ملف الطالب مع وصل A5/A4 قابل للطباعة ورابط WhatsApp برسالة جاهزة.
- محافظ مدرسين مرتبطة بالمواد، وسجل صرف قابل للتدقيق.
- جرد للمواد يعرض المشتركين والتحصيل والديون والدفعات والإلغاءات.
- تقارير قابلة للفلترة للتحصيل والصرف والخصومات وردود المبالغ.

## المتطلبات

- PHP 8.3 أو أحدث
- Composer 2
- Node.js 20 أو أحدث
- MySQL 8 أو أحدث

## تشغيل الديمو محليًا

```bash
composer install
copy .env.example .env
php artisan key:generate
```

اضبط بيانات MySQL في `.env`، ثم شغّل:

```bash
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
npm install
npm run build
php artisan serve
```

لعرض تجريبي كبير أمام الإدارة، استخدم بدلًا من ذلك:

```bash
php artisan db:seed --class=LargeDemoDataSeeder
```

الـSeeder الموسّع آمن للتكرار ولا يضاعف السجلات عند تشغيله مرة أخرى. يجهّز 180 طالبًا، 14 مدرسًا، 18 مادة، 405 اشتراكًا، دفعات وخصومات وردود وصرف مدرسين و40 حركة في دفتر اليوم.

افتح `http://127.0.0.1:8000/login`.

## حسابات الديمو

جميع الحسابات التالية تستخدم كلمة المرور: `DemoPass-2026!`

| الدور | البريد الإلكتروني |
| --- | --- |
| مدير المركز | `admin@centerdream.test` |
| سكرتير | `secretary@centerdream.test` |
| مدرس رياضيات | `math@centerdream.test` |
| مدرسة لغة إنجليزية | `english@centerdream.test` |

> بيانات الـSeeder مخصصة للعرض والاختبار فقط. غيّر كلمات المرور واحذف هذه الحسابات قبل الاستخدام الحقيقي.

## تجهيز الإنتاج

لا ترفع ملف `.env`. على الاستضافة اضبط على الأقل:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
DEMO_SEEDER_ENABLED=false
```

واجعل مجلد الموقع العام يشير إلى `public`، ثم شغّل:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan center:create-admin
npm ci
npm run build
php artisan storage:link
chmod -R ug+rwx storage bootstrap/cache
php artisan optimize
```

لا تشغّل `DemoDataSeeder` في الإنتاج؛ فهو ينشئ حسابات عرض معروفة. أنشئ أول مدير عبر عملية إعداد موثقة أو أمر إداري آمن، ثم غيّر كلمة المرور فورًا. إذا استُخدمت بيانات العرض مؤقتًا في بيئة تجريبية فقط، اضبط `DEMO_SEEDER_ENABLED=true` قبل الأمر وأعده إلى `false` ثم شغّل `php artisan config:cache`.

## الاختبارات

```bash
php artisan test --compact
```

## الترخيص

هذا المشروع مخصص لسنتر دريم.
