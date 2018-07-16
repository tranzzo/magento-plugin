## Платежный модуль TRANZZO для CMS Magento 2.1.x

Тестировался модуль на CMS Magento 2.1.9

### Установка
1. Загрузить папку **TranzzoPayment** на сервер сайта в папку **[корень_сайта]/app/code/**
2. В консоли(через SSH) перейдите в корень сайта и введите по очереди следующие команды:

php bin/magento module:enable TranzzoPayment_Tranzzo
php bin/magento cache:clean
php bin/magento cache:flush
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento indexer:reindex
php bin/magento setup:static-content:deploy

Для требуемой локализации(ru_RU,en_US) команда имеет следующий вид:
php bin/magento setup:static-content:deploy en_US

### Настройка
1. Получите ключи авторизации и идентификации у TRANZZO (*POS_ID, API_KEY, API_SECRET, ENDPOINTS_KEY*).
2. В админ. панели сайта перейти во вкладку _**Store → Configuration → Sales → Payment Methods**_ 
(_**Магазин → Конфигурации → Продажи → Методы оплаты**_)
3. Находим в списке "TRANZZO" открываем настройки и заполняем, все поля обязательны к заполнению.
