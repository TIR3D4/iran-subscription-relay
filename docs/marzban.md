# مرزبان به ایران

در نصب‌کننده «فقط مرزبان» را انتخاب کنید یا `examples/marzban.php` را به عنوان `config.php` استفاده کنید. `panels.marzban` آدرس اصلی اشتراک، مثلاً `https://marzban.example.com/sub` است؛ اگر مسیر یا پورت سفارشی دارید همان را وارد کنید.

در `.env` مرزبان، پس از بکاپ مقدار قبلی، prefix عمومی را تنظیم کنید:

```dotenv
XRAY_SUBSCRIPTION_URL_PREFIX="https://sub.example.com"
XRAY_SUBSCRIPTION_PATH="sub"
```

نام فایل و پوشه نصب بسته به روش نصب فرق دارد؛ از ابزار مدیریت همان نصب برای راه‌اندازی مجدد با محیط تازه استفاده کنید. در نصب Docker Compose، `docker compose up -d --force-recreate` را فقط از پوشه صحیح پروژه اجرا کنید تا env اعمال شود. لینک یک کاربر را دوباره از پنل دریافت و شکل نهایی آن را بررسی کنید.

منابع رسمی: [نمونه محیط](https://github.com/Gozargah/Marzban/blob/master/.env.example)، [تنظیمات](https://github.com/Gozargah/Marzban/blob/master/config.py).

[تست واقعی](testing.md): لینک اصلی و عمومی، صفحه مرورگر و فرمت کلاینت، مصرف و تاریخ انقضا. در این حالت هیچ درخواستی به پاسارگارد نمی‌رود.
