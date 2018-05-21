# PayNearMe Payment Gateway for Woocommerce

PayNearMe integration for Woocommerce.  PayNearMe is an easy way to use cash for making online purchases, paying bills and more. You can pay by scanning a barcode in one of 28,000 stores near you.

## Getting Started

This gateway needs to be installed as a wordpress plugin.  You can use it on subscription payments, only in manual subscriptions.

### Prerequisites

- Wordpress 4.7+
- Woocommerce 3.3+

### Installing

- Copy the directory into wp-content/plugins directory of your Wordpress.
- Activate plugin in the plugins section of the Wordpress admin.
- Fulfill the PayNearMe credentials and activate the gateway in settings/checkout of the Woocommerce options.
- Fulfill the payment confirmation url in your PayNearMe administration portal, the url must be 
```
https://your-store-url/wc-api/wc_gateway_paynearme
```
## Running the tests

- If you check the "Enable test mode" checkbox, the gateway will use the sandbox credentials, and will point to the sandbox server.
- You may simulate a payment or a payment fail in your sandbox system, and it will process your Woocommerce order.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/XofoSol/PayNearMeWooCommerceGateway/tags). 

## Authors

* **Rodolfo Solorzano** - *Initial work* - [XofoSol](https://github.com/XofoSol)

See also the list of [contributors](https://github.com/XofoSol/PayNearMeWooCommerceGateway/contributors) who participated in this project.

## License

This project is licensed under the GNU General Public License v3.0
Permissions of this strong copyleft license are conditioned on making available complete source code of licensed works and modifications, which include larger works using a licensed work, under the same license. Copyright and license notices must be preserved. Contributors provide an express grant of patent rights. - see the [LICENSE.md](LICENSE.md) file for details

