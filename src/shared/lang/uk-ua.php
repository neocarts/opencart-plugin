<?php
// Тексти плагіна Neocarts, українська. Набір ключів — як в en-gb.php (tests/php/LangTest.php).
return [
    'heading_title' => 'Neocarts — підписки',
    'text_extension' => 'Розширення',
    'text_success' => 'Налаштування збережено.',
    'text_enabled' => 'Увімкнено',
    'text_disabled' => 'Вимкнено',
    'text_yes' => 'Так',
    'text_no' => 'Ні',
    'text_none' => '— не змінювати —',
    'button_save' => 'Зберегти',
    'button_back' => 'Назад',
    'error_permission' => 'У вас немає прав змінювати Neocarts.',

    // вкладки
    'tab_connection' => 'Підключення',
    'tab_placement' => 'Розміщення',
    'tab_checkout' => 'Оформлення',
    'tab_orders' => 'Замовлення',

    // підключення
    'entry_status' => 'Статус',
    'entry_shop_id' => 'ID магазину',
    'help_shop_id' => 'Панель Neocarts → Налаштування → Інтеграція → «ID магазину».',
    'entry_api_key' => 'API-ключ',
    'help_api_key' => 'nc_live_… з тієї ж вкладки. Там він показується один раз; тут зберігається і більше не відображається. Порожнє поле — лишити поточний ключ.',
    'text_api_key_set' => 'Ключ збережено',
    'entry_webhook_secret' => 'Секрет підпису вебхуків',
    'help_webhook_secret' => 'whsec_… з розділу «Вебхуки» на вкладці «Інтеграція». Порожнє поле — лишити поточний секрет.',
    'entry_api_url' => 'Адреса Shop API',
    'help_api_url' => 'Змінюйте лише для staging, за вказівкою підтримки Neocarts.',
    'entry_webhook_url' => 'URL вебхука',
    'help_webhook_url' => 'Вставте його в панелі Neocarts: Налаштування → Інтеграція → «URL для вебхуків». Production приймає лише https://.',
    'button_check' => 'Перевірити підключення',
    'text_check_ok' => 'Ключ працює.',
    'text_check_unauthorized' => 'Ключ неправильний або відкликаний.',
    'text_check_unreachable' => 'Shop API недоступний з цього сервера.',
    'text_check_unexpected' => 'Неочікувана відповідь Shop API — дивіться журнал.',
    'text_version' => 'Версія плагіна',
    'button_neocarts_admin' => 'Відкрити панель Neocarts',
    'warning_maintenance' => 'Магазин у режимі обслуговування: вебхуки Neocarts не дійдуть, доки його не вимкнути.',

    // розміщення
    'legend_product' => 'Сторінка товару',
    'legend_cards' => 'Картки товарів (списки й модулі)',
    'legend_cart' => 'Рядки кошика',
    'entry_anchor' => 'Якір (CSS-селектор)',
    'entry_position' => 'Позиція',
    'entry_class' => 'Додатковий CSS-клас',
    'entry_card_selector' => 'Картка (CSS-селектор)',
    'entry_card_id_regex' => 'ID товару в картці (regex, група 1)',
    'entry_cart_row_selector' => 'Рядок кошика (CSS-селектор)',
    'entry_cart_key_regex' => 'Ключ рядка кошика (regex, група 1)',
    'entry_card_pages' => 'Показувати на сторінках',
    'text_position_before' => 'Перед якорем',
    'text_position_after' => 'Після якоря',
    'text_position_prepend' => 'Всередині, на початку',
    'text_position_append' => 'Всередині, в кінці',
    'text_page_home' => 'Головна',
    'text_page_category' => 'Категорія',
    'text_page_search' => 'Пошук',
    'text_page_special' => 'Акції',
    'text_page_manufacturer' => 'Бренд',
    'text_page_product' => 'Сторінка товару (схожі товари)',
    'text_page_other' => 'Інші сторінки',
    'help_placement' => 'Значення за замовчуванням підходять до стандартної теми. Для іншої теми вкажіть селектори її розмітки.',
    'help_options' => 'Товари з обовʼязковими опціями отримують кнопку лише в кошику, де вибрані опції вже відомі.',

    // оформлення
    'entry_checkout_intercept' => 'Оформлювати кошик з підпискою через Neocarts',
    'help_checkout_intercept' => '«Оформити замовлення» відкриває оформлення Neocarts, якщо в кошику є хоча б одна підписка. Кошик без підписок оформлюється магазином як звичайно.',
    'entry_checkout_link_selector' => 'Посилання на оформлення (CSS-селектор)',
    'entry_checkout_banner' => 'Банер на сторінці оформлення',
    'entry_button_checkout_url' => '«Перейти до оформлення» у вікні підписки',
    'entry_auto_add' => 'Додавати товар у кошик, коли покупець оформив підписку',

    // замовлення
    'entry_orders_widget' => 'Створювати замовлення після оплати в Neocarts',
    'entry_orders_renewal' => 'Створювати замовлення на кожне поновлення підписки',
    'entry_status_paid' => 'Статус: оплачено',
    'entry_status_manual' => 'Статус: оплату підтверджено вручну',
    'entry_status_cancelled' => 'Статус: скасовано',
    'entry_status_refunded' => 'Статус: повернено',
    'entry_status_partially_refunded' => 'Статус: частково повернено',
    'entry_notify_customer' => 'Повідомляти покупця листом про зміну статусу',

    // підписки

    // товари
    'entry_product_sync' => 'Надсилати картку товару в Neocarts під час збереження товару',
    'help_product_sync' => 'Назва, варіант, зображення й посилання. Ціна автоматично не надсилається ніколи.',
    'heading_product_panel' => 'Підписки Neocarts',

    // журнал
    'entry_debug' => 'Докладний журнал',

    // замовлення з вебхуків
    'order_comment' => 'Neocarts №%1$s · %2$s · %3$s',
    'order_source_widget' => 'оформлення підписки',
    'order_source_renewal' => 'поновлення підписки',
    'order_confirmed_manually' => 'Оплату підтверджено вручну в панелі Neocarts.',
    'order_refunded' => 'Повернення в Neocarts: %s.',
    'order_cancelled' => 'Скасовано в Neocarts.',
    'order_unknown_product' => 'Товар «%s» (%s) не знайдено в магазині; рядок додано за назвою.',
    'payment_method' => 'Neocarts',
    'delivery_nova_poshta' => 'Нова Пошта',
    'delivery_ukr_poshta' => 'Укрпошта',
    'delivery_type_branch' => 'відділення',
    'delivery_type_parcel_locker' => 'поштомат',
    'delivery_type_address' => 'курʼєр',
    'address_apartment' => 'кв. %s',
    'address_floor' => 'поверх %s',
    'account_subscriptions' => 'Мої підписки',

    // вітрина (inline-конфіг)
    'sf_checkout_banner' => 'У кошику є підписка: її оформлення проходить через Neocarts.',
    'sf_checkout_button' => 'Оформити підписку',
    'sf_busy' => 'Оформлюємо…',
    'sf_error' => 'Не вдалося оформити замовлення. Спробуйте ще раз.',
    'sf_not_ready' => 'Підписки тимчасово недоступні. Спробуйте пізніше.',
    'sf_order_missing' => 'Замовлення більше недоступне — оформіть нове.',

    // помилки збирання замовлення (Items::build)
    'error_cart_empty' => 'Кошик порожній.',
    'error_too_many_lines' => 'У замовленні з підпискою може бути не більше 50 рядків.',
    'error_quantity' => '«%s»: у замовленні з підпискою не більше 9 одиниць у рядку.',
    'error_price' => '«%s»: ціна поза допустимими межами.',
    'error_interval' => '«%s»: неправильний інтервал підписки.',
    'error_no_subscription' => 'У кошику немає підписки.',

    // admin menu, order totals, list filters (added by the 2.3 adapter, used by all)
    'order_total_discount' => 'Знижка підписника',
    'button_cancel' => 'Скасувати',
    'text_auto' => 'Автоматично',

    // підтримка (Neocarts\Links)
    'text_support' => 'Питання чи проблеми? Пишіть у підтримку Neocarts: %s',
    'text_support_short' => 'Підтримка Neocarts',
    'text_docs' => 'Документація плагіна',
    'text_check_support' => 'Якщо не допомогло, напишіть на %s і додайте журнал.',

    // перемикач для товару, наявність, ціна переоцінки, власний CSS
    'entry_product_subscription' => 'Підписка доступна для цього товару',
    'help_product_subscription' => 'За замовчуванням увімкнено для всіх товарів. Вимкнено — кнопки підписки немає, товар продається лише разово. Товари, яких немає в наявності, кнопки теж не мають.',
    'entry_custom_css' => 'Власний CSS',
    'help_custom_css' => 'Додається після стилів плагіна на кожній сторінці, напр. neocarts-button.neocarts-oc-button { margin-top: 16px }',

    // блок товару
    'text_neocarts_panel_link' => 'Підписки та їхні ціни — у панелі Neocarts',
    'entry_account_link' => 'Посилання «Мої підписки» в кабінеті покупця',

    // теми: пресети, запасні селектори, швидкий перегляд, міні-кошик, «в один клік», ручний слот
    'text_theme_preset' => 'Тема магазину «%s»: її селектори застосовано всюди, де ви лишили значення за замовчуванням.',
    'text_theme_no_preset' => 'Тема магазину «%s»: пресету немає, використано селектори стандартної теми. Змініть їх нижче, якщо кнопки немає або вона не на місці.',
    'help_selector_fallback' => 'Кілька селекторів через «||» перевіряються по черзі; береться перший, що знайшовся.',
    'entry_quickview_anchor' => 'Вікно швидкого перегляду: якір (CSS-селектор)',
    'help_quickview_anchor' => 'Якщо тема відкриває товар у спливному вікні. Порожньо — кнопки там немає.',
    'entry_minicart_selector' => 'Міні-кошик (CSS-селектор)',
    'entry_minicart_url' => 'Адреса оновлення міні-кошика',
    'help_minicart_url' => 'Порожньо — стандартна адреса магазину.',
    'entry_oneclick_selector' => 'Кнопки «Купити в один клік» (CSS-селектор)',
    'help_oneclick_selector' => 'Ховаються, поки покупець обрав підписку: такі замовлення минають кошик і не можуть бути підпискою.',
    'entry_checkout_routes' => 'Сторінки оформлення (маршрути через кому)',
    'help_slot' => 'Будь-яке місце теми, HTML-модуля чи конструктора сторінок: <div data-neocarts-slot data-product-id="42"></div> отримає кнопку товару 42.',

    // the box around each button
    'entry_style' => 'Стиль блоку (inline CSS)',
    'help_style' => 'CSS блоку навколо кнопки, напр. margin: 10px 0; — клас вище ставиться на цей самий блок.',
];
