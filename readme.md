<h1>Invoice Woocomerce plugin</h1>

<h3>Установка</h3>

1. [Скачайте плагин](https://github.com/Invoice-LLC/Invoice.Module.WooCommerce/archive/master.zip) и скопируйте содержимое архива в папку %корень сайта%/wp-content/plugins/
2. Перейдите во вкладку плагины<br>
![Imgur](https://imgur.com/XxHlkuq.png)
3. Активируйте плагин<br>
![Imgur](https://imgur.com/3KaIS1T.png)
4. Перейдите во вкладку WooComerce->Настройки->Платежи и включите метод оплаты "Invoce"<br>
![Imgur](https://imgur.com/WBQxGx3.png)
5. В той же вкладке перейдите в управление методом оплаты<br>
![Imgur](https://imgur.com/AioEmVq.png)
5. Введите ключ API и логин от личного кабинета<br>
![Imgur](https://imgur.com/YUphf8X.png)<br>
(Все данные вы можете получить в [личном кабинете Invoice](https://lk.invoice.su/))
6. Добавьте уведомление в личном кабинете Invoice(Вкладка Настройки->Уведомления->Добавить)
с типом **WebHook** и адресом: **%URL сайта%/?wc-api=invoice**
![Imgur](https://imgur.com/LZEozhf.png)
