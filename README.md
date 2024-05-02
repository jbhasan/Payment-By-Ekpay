# Payment by Ekpay

## Integration

##### Follow below instructions for installing the package

##### Step 1

```shell
composer require sayeed/payment-by-ekpay
```

##### Step 2

```shell
php artisan migrate
```

##### Step 3

NB: Your `timezone` in `app.php` must be `Asia/Dhaka`

Put below information in `.env` file

```config
EKPAY_API_DOMAIN=<EKPAY_DOMAIN_ENDPOINT>
EKPAY_MERCHANT_ID=<EKPAY_MERCHANT_ID>
EKPAY_MERCHANT_PASSWORD=<EKPAY_MERCHANT_PASSWORD>
EKPAY_WHITELISTED_IP=<EKPAY_WHITELISTED_IP>
EKPAY_SUCCESS_URL=/success_payment
EKPAY_FAILED_URL=/failed_payment
EKPAY_CANCEL_URL=/cancel_payment
EKPAY_IPN_URL=/ipn
EKPAY_IPN_EMAIL=<IPN_EMAIL>
```

```
php artisan config:clear
```

```
php artisan route:clear
```

```
php artisan vendor:publish  --provider="Sayeed\PaymentByEkpay\Providers\PaymentByEkpayServiceProvider"
```

##### Step 4 (Uses)

Submit your request to `/ekpay/pay` route with params:

-   amount
-   customer_name
-   customer_email
-   customer_mobile
-   product_name
-   customer_address [optional]
-   customer_country [optional]

See example in `resources/views/ekpay_example.blade.php`

##### Step 5 (Uses)

After successful request you will get a base64 encoded data with status and message, which is shown as:

`{"status":"completed", "transaction_id":"63jk232h323d", "message":"Transaction is successfully Completed"}`

Then you can get full response from `ekpay_orders` table by using `transaction_id`

## Credits

-   [Md. Hasan Sayeed](https://github.com/jbhasan)

Thank you for using it.
