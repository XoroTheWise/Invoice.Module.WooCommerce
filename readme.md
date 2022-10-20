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
6. Введите ключ API и Merchant Id от личного кабинета<br>
![image](https://user-images.githubusercontent.com/91345275/196219292-88f8af1e-a777-4f26-983c-8f718a08de1b.png)<br>
Все данные вы можете получить в [личном кабинете Invoice](https://lk.invoice.su/)<br>
<br>Api ключи и Merchant Id:<br>
![image](https://user-images.githubusercontent.com/91345275/196218699-a8f8c00e-7f28-451e-9750-cfa1f43f15d8.png)
![image](https://user-images.githubusercontent.com/91345275/196218722-9c6bb0ae-6e65-4bc4-89b2-d7cb22866865.png)<br>
<br>Terminal Id:<br>
![image](https://user-images.githubusercontent.com/91345275/196218998-b17ea8f1-3a59-434b-a854-4e8cd3392824.png)
![image](https://user-images.githubusercontent.com/91345275/196219014-45793474-6dfa-41e3-945d-fc669c916aca.png)

6. Добавьте уведомление в личном кабинете Invoice(Вкладка Настройки->Уведомления->Добавить)
с типом **WebHook** и адресом: **%URL сайта%/?wc-api=invoice**
![Imgur](https://imgur.com/LZEozhf.png)