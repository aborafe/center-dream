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
```

واجعل مجلد الموقع العام يشير إلى `public`، ثم شغّل:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan db:seed --class=DemoDataSeeder --force
npm ci
npm run build
php artisan optimize
```

لإنشاء بيانات العرض على إنتاج للمرة الأولى فقط، اضبط `DEMO_SEEDER_ENABLED=true` في `.env` قبل أمر الـSeeder، ثم أعده فورًا إلى `false` وشغّل `php artisan config:cache`.

## الاختبارات

```bash
php artisan test --compact
```

## الترخيص

هذا المشروع مخصص لسنتر دريم.
