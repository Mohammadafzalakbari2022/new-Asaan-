// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Persian (`fa`).
class AppLocalizationsFa extends AppLocalizations {
  AppLocalizationsFa([String locale = 'fa']) : super(locale);

  @override
  String get appTitle => 'آسان استور';

  @override
  String get navHome => 'خانه';

  @override
  String get navCategories => 'دسته‌بندی‌ها';

  @override
  String get navCart => 'سبد خرید';

  @override
  String get navWishlist => 'علاقه‌مندی‌ها';

  @override
  String get navProfile => 'پروفایل';

  @override
  String get commonTryAgain => 'دوباره تلاش کنید';

  @override
  String get commonDone => 'تمام';

  @override
  String get commonCancel => 'لغو';

  @override
  String get commonSave => 'ذخیره';

  @override
  String get commonDiscard => 'انصراف';

  @override
  String get commonKeepEditing => 'ادامه ویرایش';

  @override
  String get commonLanguage => 'زبان';

  @override
  String get languageTitle => 'زبان';

  @override
  String get languageSubtitle => 'زبان برنامه را انتخاب کنید.';

  @override
  String get languageSystem => 'پیش‌فرض سیستم';

  @override
  String get languageLoadError => 'بارگیری زبان‌های موجود ناکام شد.';

  @override
  String get servicesTitle => 'خدمات';

  @override
  String get servicesSearchHint => 'جستجوی خدمات';

  @override
  String get servicesAll => 'همه';

  @override
  String get servicesEmpty => 'هیچ خدمتی با جستجوی شما مطابقت ندارد.';

  @override
  String get servicesLoadError => 'خدمات بارگیری نشد. دوباره تلاش کنید.';

  @override
  String get servicesDetailsTitle => 'جزئیات خدمت';

  @override
  String get servicesAbout => 'درباره این خدمت';

  @override
  String get servicesIncluded => 'شامل چه چیزهایی است';

  @override
  String get servicesExcluded => 'شامل نیست';

  @override
  String get servicesBooking => 'رزرو';

  @override
  String get servicesInfoOnly =>
      'این خدمت فقط برای اطلاع نمایش داده شده و اکنون قابل رزرو نیست.';

  @override
  String get servicesBookButton => 'این خدمت را رزرو کنید';

  @override
  String get servicesAlsoNeed => 'ممکن است این را هم لازم داشته باشید';

  @override
  String get servicesFeatured => 'ویژه';

  @override
  String get servicesDetailLoadError =>
      'این خدمت بارگیری نشد. دوباره تلاش کنید.';

  @override
  String get bookingTitle => 'رزرو یک خدمت';

  @override
  String get bookingIntro =>
      'بگویید کجا و چه زمانی بیاییم. فروشگاه به‌زودی تأیید می‌کند.';

  @override
  String get bookingName => 'نام شما';

  @override
  String get bookingNameRequired =>
      'نام خود را بگویید تا بدانیم با چه کسی صحبت کنیم.';

  @override
  String get bookingPhone => 'شماره تلفن';

  @override
  String get bookingPhoneRequired =>
      'شماره تلفن لازم است تا کارگر با شما تماس بگیرد.';

  @override
  String get bookingPhoneShort => 'این شماره تلفن خیلی کوتاه به نظر می‌رسد.';

  @override
  String get bookingEmail => 'ایمیل (اختیاری)';

  @override
  String get bookingEmailInvalid => 'این ایمیل معتبر نیست.';

  @override
  String get bookingAddress => 'آدرسی که کار در آن لازم است';

  @override
  String get bookingAddressRequired => 'آدرسی که کار در آن لازم است را بگویید.';

  @override
  String get bookingAddressShort =>
      'لطفاً آدرس کامل را همراه با ناحیه بنویسید.';

  @override
  String get bookingCity => 'شهر / ناحیه (اختیاری)';

  @override
  String get bookingDate => 'تاریخ';

  @override
  String get bookingPickDate => 'یک تاریخ انتخاب کنید';

  @override
  String get bookingDateRequired =>
      'روزی که می‌خواهید کار انجام شود را انتخاب کنید.';

  @override
  String get bookingTime => 'زمان روز';

  @override
  String get bookingPickTime => 'یک زمان انتخاب کنید';

  @override
  String get bookingTimeRequired => 'زمان روز را انتخاب کنید.';

  @override
  String get bookingNotes => 'چیز دیگری؟ (اختیاری)';

  @override
  String get bookingSend => 'ارسال رزرو';

  @override
  String get bookingCalendarError => 'تقویم رزرو بارگیری نشد.';

  @override
  String get bookingSendFailed => 'رزرو شما ارسال نشد. دوباره تلاش کنید.';

  @override
  String get bookingDiscardTitle => 'این رزرو رد شود؟';

  @override
  String get bookingDiscardBody =>
      'شما اطلاعاتی نوشته‌اید که هنوز ارسال نشده است. اکنون خارج شدن آن‌ها را از بین می‌برد.';

  @override
  String get bookingSentTitle => 'رزرو ارسال شد';

  @override
  String get bookingSentBody => 'فروشگاه به‌زودی رزرو شما را تأیید می‌کند.';

  @override
  String get bookingReference => 'مرجع';

  @override
  String get bookingServiceLabel => 'خدمت';

  @override
  String get bookingPhoneLabel => 'تلفن';

  @override
  String referralShareBody(String threshold, String reward, int days) {
    return 'لینک خود را شریک کنید. وقتی دوستی عضو شود و $threshold خرج کند، شما $reward اعتبار فروشگاهی به‌دست می‌آورید. اعتبار پس از $days روز آزاد می‌شود.';
  }

  @override
  String referralSpentSoFar(String amount) {
    return 'تا کنون $amount خرج شده';
  }

  @override
  String referralJoined(String date) {
    return 'پیوست $date';
  }

  @override
  String referralLockedUntil(String date) {
    return 'تا $date قفل است';
  }

  @override
  String referralVerifiedOn(String date) {
    return 'در $date تأیید شد.';
  }

  @override
  String referralSubmittedOn(String date) {
    return 'در $date ارسال شد. تیم ما به‌زودی بررسی می‌کند.';
  }

  @override
  String identityAcceptedFormats(String types, String max) {
    return 'فرمت‌های پذیرفته‌شده: $types · حداکثر اندازه: $max';
  }

  @override
  String get profileHeaderAccount => 'حساب';

  @override
  String get profileHeaderSupport => 'پشتیبانی';

  @override
  String get profileMyOrders => 'سفارش‌های من';

  @override
  String get profileAddresses => 'آدرس‌ها';

  @override
  String get profileIdentity => 'تأیید هویت';

  @override
  String get profileReferrals => 'معرفی دوستان';

  @override
  String get profileServices => 'خدمات';

  @override
  String get profileHelpSupport => 'راهنما و پشتیبانی';

  @override
  String get profileChatWhatsApp => 'گفتگو در واتساپ';

  @override
  String get profileGetApp => 'دریافت اپلیکیشن اندروید';

  @override
  String get profileAboutUs => 'درباره ما';

  @override
  String get profileTerms => 'شرایط و ضوابط';

  @override
  String get profilePrivacy => 'سیاست حفظ حریم خصوصی';

  @override
  String get profileEditProfile => 'ویرایش پروفایل';

  @override
  String get referralsTitle => 'معرفی دوستان';

  @override
  String get referralProgramOff =>
      'برنامه معرفی در حال حاضر خاموش است. شما هنوز می‌توانید موجودی خود را ببینید.';

  @override
  String get referralCreditAvailable => 'اعتبار موجود';

  @override
  String get referralPending => 'در انتظار';

  @override
  String get referralEarned => 'کسب‌شده';

  @override
  String get referralUsed => 'استفاده‌شده';

  @override
  String get referralYourCode => 'کد معرفی شما';

  @override
  String get referralCopyCode => 'کاپی کد';

  @override
  String get referralCopiedCode => 'کد کاپی شد.';

  @override
  String get referralCopiedLink => 'لینک کاپی شد.';

  @override
  String get referralApplyTitle => 'کد دوست را وارد کنید';

  @override
  String get referralApplyHint =>
      'کدی را که دوستی با شما شریک کرده وارد کنید. فقط یک بار و فقط در حسابی که هنوز کد ندارد قابل استفاده است.';

  @override
  String get referralApplyFieldHint => 'مثال: AHMAD-7K2QX';

  @override
  String get referralApplyButton => 'اعمال کد';

  @override
  String get referralApplyFailed => 'کد اعمال نشد. دوباره تلاش کنید.';

  @override
  String get referralApplied => 'کد معرفی اعمال شد.';

  @override
  String get referralInvitedTitle => 'دوستانی که دعوت کردید';

  @override
  String get referralInvitedEmpty =>
      'هنوز هیچ‌کس. برای شروع لینک خود را شریک کنید.';

  @override
  String get referralRewarded => 'پاداش داده شد';

  @override
  String get referralVoided => 'باطل شد';

  @override
  String get referralRewardPending => 'پاداش در انتظار';

  @override
  String get referralHistoryTitle => 'تاریخچه اعتبار';

  @override
  String get referralHistoryEmpty => 'هنوز فعالیت اعتباری وجود ندارد.';

  @override
  String get referralDiscardTitle => 'تغییرات خود را رد کنید؟';

  @override
  String get referralDiscardBody => 'کد معرفی که وارد کردید اعمال نشده است.';

  @override
  String get referralLedgerCredit => 'اعتبار';

  @override
  String get referralLedgerReward => 'پاداش معرفی';

  @override
  String get referralLedgerAdminCredit => 'اعتبار فروشگاهی اضافه شد';

  @override
  String get referralLedgerAdminDebit => 'اصلاح';

  @override
  String get identityTitle => 'تأیید هویت';

  @override
  String get identityVerified => 'تأییدشده';

  @override
  String get identityNotAccepted => 'نه پذیرفته شد';

  @override
  String get identityUnderReview => 'در حال بررسی';

  @override
  String get identityNotVerified => 'هنوز تأیید نشده';

  @override
  String get identityVerifiedDefault => 'هویت شما تأیید شده است.';

  @override
  String get identityRejectedDefault =>
      'ارسال آخر شما پذیرفته نشد. می‌توانید دوباره تلاش کنید.';

  @override
  String get identityNotVerifiedBody =>
      'تذکره خود را تأیید کنید تا از معرفی دوستان درآمد کسب کنید و حساب خود را امن نگه دارید.';

  @override
  String get identityVerifiedNotice =>
      'هویت شما تأیید شده است. چیز دیگری لازم نیست.';

  @override
  String get identityPendingNotice =>
      'شما از قبل یک تذکره در حال بررسی دارید. پس از بررسی به شما اطلاع می‌دهیم.';

  @override
  String get identityNumberLabel => 'شماره تذکره';

  @override
  String get identityNumberHint => 'شماره تذکره';

  @override
  String get identityNumberEmpty => 'شماره تذکره خود را وارد کنید.';

  @override
  String get identityNumberInvalid =>
      'این شماره تذکره درست به نظر نمی‌رسد. ارقام را همان‌طور که چاپ شده وارد کنید.';

  @override
  String get identityFullNameLabel => 'نام کامل (همان‌طور که در تذکره است)';

  @override
  String get identityFullNameHint => 'نام کامل شما';

  @override
  String get identityFullNameEmpty =>
      'نام کامل خود را دقیقاً همان‌طور که در تذکره نوشته شده وارد کنید.';

  @override
  String get identityFatherNameLabel => 'نام پدر (اختیاری)';

  @override
  String get identityFatherNameHint => 'نام پدر';

  @override
  String get identityDateLabel => 'تاریخ تولد (اختیاری)';

  @override
  String get identitySelectDate => 'انتخاب تاریخ';

  @override
  String get identityPhotoLabel => 'عکس تذکره شما';

  @override
  String get identityPhotoTap => 'برای افزودن عکس ضربه بزنید';

  @override
  String get identityPhotoMissing =>
      'عکس تذکره خود را اضافه کنید تا تیم ما آن را بررسی کند.';

  @override
  String get identitySubmit => 'ارسال برای بررسی';

  @override
  String get identitySubmitted =>
      'تذکره ارسال شد. تیم ما به‌زودی بررسی می‌کند.';

  @override
  String get identityLoadError => 'وضعیت تأیید شما بارگیری نشد.';

  @override
  String get identitySubmitError => 'مشکلی پیش آمد. دوباره تلاش کنید.';

  @override
  String get identityDiscardTitle => 'تغییرات خود را رد کنید؟';

  @override
  String get identityDiscardBody =>
      'شما اطلاعاتی وارد کرده‌اید که هنوز ارسال نشده است.';

  @override
  String get identityPhotoError => 'عکس‌های شما باز نشد.';

  @override
  String get commonError => 'مشکلی پیش آمد. دوباره تلاش کنید.';

  @override
  String get commonShare => 'شریک کردن';

  @override
  String servicesContactCall(String phone) {
    return 'تماس $phone';
  }

  @override
  String servicesContactWhatsapp(String phone) {
    return 'واتس‌اپ $phone';
  }

  @override
  String get referralApplyEmpty => 'کدی را که به شما داده شده وارد کنید.';

  @override
  String get referralHaveCode => 'کد معرفی دارید؟';

  @override
  String referralJoinedWith(String name) {
    return 'شما با کد معرفی $name عضو شدید.';
  }

  @override
  String get referralLedgerSpent => 'استفاده در یک سفارش';

  @override
  String get referralLedgerReversed => 'برگشت‌خورده';

  @override
  String identityVerifiedOn(String date) {
    return 'در $date تأیید شد.';
  }

  @override
  String identitySubmittedOn(String date) {
    return 'در $date ارسال شد. تیم ما به‌زودی بررسی می‌کند.';
  }

  @override
  String get identityNumberExample => 'مثلاً ۱۴۰۱ ۵۶۷۸ ۹۰۱۲';

  @override
  String get identityFullNameShort => 'این نام خیلی کوتاه به نظر می‌رسد.';
}
