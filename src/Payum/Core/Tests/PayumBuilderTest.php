<?php

namespace Payum\Core\Tests;

use LogicException;
use Omnipay\Dummy\Gateway as OmnipayGateway;
use Payum\AuthorizeNet\Aim\AuthorizeNetAimGatewayFactory;
use Payum\Be2Bill\Be2BillDirectGatewayFactory;
use Payum\Be2Bill\Be2BillOffsiteGatewayFactory;
use Payum\Core\Bridge\PlainPhp\Security\HttpRequestVerifier;
use Payum\Core\CoreGatewayFactory;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\Extension\StorageExtension;
use Payum\Core\Gateway;
use Payum\Core\GatewayFactory;
use Payum\Core\GatewayFactoryInterface;
use Payum\Core\Model\ArrayObject;
use Payum\Core\Model\Payment;
use Payum\Core\Model\Payout;
use Payum\Core\Payum;
use Payum\Core\PayumBuilder;
use Payum\Core\Registry\RegistryInterface;
use Payum\Core\Registry\SimpleRegistry;
use Payum\Core\Registry\StorageRegistryInterface;
use Payum\Core\Security\GenericTokenFactory;
use Payum\Core\Security\GenericTokenFactoryInterface;
use Payum\Core\Security\HttpRequestVerifierInterface;
use Payum\Core\Security\TokenFactoryInterface;
use Payum\Core\Storage\StorageInterface;
use Payum\Klarna\Checkout\KlarnaCheckoutGatewayFactory;
use Payum\Klarna\Invoice\KlarnaInvoiceGatewayFactory;
use Payum\Offline\OfflineGatewayFactory;
use Payum\OmnipayV3Bridge\OmnipayGatewayFactory;
use Payum\Payex\PayexGatewayFactory;
use Payum\Paypal\ExpressCheckout\Nvp\PaypalExpressCheckoutGatewayFactory;
use Payum\Paypal\Masspay\Nvp\PaypalMasspayGatewayFactory;
use Payum\Paypal\ProCheckout\Nvp\PaypalProCheckoutGatewayFactory;
use Payum\Paypal\Rest\PaypalRestGatewayFactory;
use Payum\Stripe\StripeCheckoutGatewayFactory;
use Payum\Stripe\StripeJsGatewayFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

class PayumBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [
            'HTTP_HOST' => 'payum.dev',
        ];
    }

    public function testThrowsIfTokenStorageIsNotSet(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Token storage must be configured.');
        $payum = (new PayumBuilder())->getPayum();

        $this->assertInstanceOf(Payum::class, $payum);
    }

    public function testShouldBuildDefaultPayum(): void
    {
        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertInstanceOf(HttpRequestVerifier::class, $payum->getHttpRequestVerifier());
        $this->assertInstanceOf(GenericTokenFactory::class, $payum->getTokenFactory());

        $this->assertIsArray($payum->getGateways());
        $this->assertCount(0, $payum->getGateways());

        $this->assertIsArray($payum->getStorages());
        $this->assertCount(3, $payum->getStorages());
        $this->assertArrayHasKey(Payment::class, $payum->getStorages());
        $this->assertArrayHasKey(ArrayObject::class, $payum->getStorages());
        $this->assertArrayHasKey(Payout::class, $payum->getStorages());

        $factories = $payum->getGatewayFactories();
        $this->assertIsArray($factories);
        $this->assertGreaterThan(40, $factories);

        $this->assertArrayHasKey('paypal_express_checkout', $factories);
        $this->assertInstanceOf(PaypalExpressCheckoutGatewayFactory::class, $factories['paypal_express_checkout']);

        $this->assertArrayHasKey('paypal_masspay', $factories);
        $this->assertInstanceOf(PaypalMasspayGatewayFactory::class, $factories['paypal_masspay']);

        $this->assertArrayHasKey('paypal_pro_checkout', $factories);
        $this->assertInstanceOf(PaypalProCheckoutGatewayFactory::class, $factories['paypal_pro_checkout']);

        $this->assertArrayHasKey('paypal_rest', $factories);
        $this->assertInstanceOf(PaypalRestGatewayFactory::class, $factories['paypal_rest']);

        $this->assertArrayHasKey('authorize_net_aim', $factories);
        $this->assertInstanceOf(AuthorizeNetAimGatewayFactory::class, $factories['authorize_net_aim']);

        $this->assertArrayHasKey('be2bill_direct', $factories);
        $this->assertInstanceOf(Be2BillDirectGatewayFactory::class, $factories['be2bill_direct']);

        $this->assertArrayHasKey('be2bill_offsite', $factories);
        $this->assertInstanceOf(Be2BillOffsiteGatewayFactory::class, $factories['be2bill_offsite']);

        $this->assertArrayHasKey('klarna_checkout', $factories);
        $this->assertInstanceOf(KlarnaCheckoutGatewayFactory::class, $factories['klarna_checkout']);

        $this->assertArrayHasKey('klarna_invoice', $factories);
        $this->assertInstanceOf(KlarnaInvoiceGatewayFactory::class, $factories['klarna_invoice']);

        $this->assertArrayHasKey('offline', $factories);
        $this->assertInstanceOf(OfflineGatewayFactory::class, $factories['offline']);

        $this->assertArrayHasKey('payex', $factories);
        $this->assertInstanceOf(PayexGatewayFactory::class, $factories['payex']);

        $this->assertArrayHasKey('stripe_checkout', $factories);
        $this->assertInstanceOf(StripeCheckoutGatewayFactory::class, $factories['stripe_checkout']);

        $this->assertArrayHasKey('stripe_js', $factories);
        $this->assertInstanceOf(StripeJsGatewayFactory::class, $factories['stripe_js']);
    }

    public function testShouldUseCustomHttpRequestVerifier(): void
    {
        /** @var HttpRequestVerifierInterface $expectedVerifier */
        $expectedVerifier = $this->createMock(HttpRequestVerifierInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setHttpRequestVerifier($expectedVerifier)
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedVerifier, $payum->getHttpRequestVerifier());
    }

    public function testShouldUseHttpRequestVerifierBuilder(): void
    {
        /** @var HttpRequestVerifierInterface $expectedVerifier */
        $expectedVerifier = $this->createMock(HttpRequestVerifierInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setHttpRequestVerifier(function ($tokenStorage) use ($expectedVerifier) {
                $this->assertInstanceOf(StorageInterface::class, $tokenStorage);

                return $expectedVerifier;
            })
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedVerifier, $payum->getHttpRequestVerifier());
    }

    public function testThrowsIfHttpRequestVerifierBuilderReturnsInvalidInstance(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Builder returned invalid instance');
        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setHttpRequestVerifier(fn () => new stdClass())
            ->getPayum()
        ;
    }

    public function testShouldUseCustomGenericTokenFactory(): void
    {
        /** @var GenericTokenFactoryInterface $expectedTokenFactory */
        $expectedTokenFactory = $this->createMock(GenericTokenFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setGenericTokenFactory($expectedTokenFactory)
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedTokenFactory, $payum->getTokenFactory());
    }

    public function testShouldUseGenericTokenFactoryBuilder(): void
    {
        /** @var GenericTokenFactoryInterface $expectedTokenFactory */
        $expectedTokenFactory = $this->createMock(GenericTokenFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setGenericTokenFactory(function ($tokenFactory, $paths) use ($expectedTokenFactory) {
                $this->assertInstanceOf(TokenFactoryInterface::class, $tokenFactory);

                $this->assertIsArray($paths);
                $this->assertSame([
                    'capture' => 'capture.php',
                    'notify' => 'notify.php',
                    'authorize' => 'authorize.php',
                    'refund' => 'refund.php',
                    'payout' => 'payout.php',
                ], $paths);

                return $expectedTokenFactory;
            })
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedTokenFactory, $payum->getTokenFactory());
    }

    public function testShouldUseCustomGenericTokenFactoryPaths(): void
    {
        $expectedPaths = [
            'capture' => 'capture_custom.php',
            'notify' => 'notify_custom.php',
            'authorize' => 'authorize_custom.php',
            'refund' => 'refund_custom.php',
            'payout' => 'payout_custom.php',
        ];

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setGenericTokenFactoryPaths($expectedPaths)
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);

        $genericTokenFactory = $payum->getTokenFactory();
        $ref = new ReflectionProperty($genericTokenFactory, 'paths');
        $ref->setAccessible(true);

        $this->assertSame($expectedPaths, $ref->getValue($genericTokenFactory));
    }

    public function testThrowsIfGenericTokenFactoryBuilderReturnInvalidInstance(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Builder returned invalid instance');
        (new PayumBuilder())
            ->addDefaultStorages()
            ->setGenericTokenFactory(fn () => new stdClass())
            ->getPayum()
        ;
    }

    public function testShouldUseCustomTokenFactory(): void
    {
        /** @var TokenFactoryInterface $expectedTokenFactory */
        $expectedTokenFactory = $this->createMock(TokenFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setTokenFactory($expectedTokenFactory)
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);

        $genericTokenFactory = $payum->getTokenFactory();
        $ref = new ReflectionProperty($genericTokenFactory, 'tokenFactory');
        $ref->setAccessible(true);

        $this->assertSame($expectedTokenFactory, $ref->getValue($genericTokenFactory));
    }

    public function testShouldUseTokenFactoryBuilder(): void
    {
        /** @var TokenFactoryInterface $expectedTokenFactory */
        $expectedTokenFactory = $this->createMock(TokenFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setTokenFactory(function ($tokenStorage, $storageRegistry) use ($expectedTokenFactory) {
                $this->assertInstanceOf(StorageInterface::class, $tokenStorage);
                $this->assertInstanceOf(StorageRegistryInterface::class, $storageRegistry);

                return $expectedTokenFactory;
            })
            ->getPayum()
        ;

        $genericTokenFactory = $payum->getTokenFactory();
        $ref = new ReflectionProperty($genericTokenFactory, 'tokenFactory');
        $ref->setAccessible(true);

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedTokenFactory, $ref->getValue($genericTokenFactory));
    }

    public function testThrowsIfTokenFactoryBuilderReturnInvalidInstance(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Builder returned invalid instance');
        (new PayumBuilder())
            ->addDefaultStorages()
            ->setTokenFactory(fn () => new stdClass())
            ->getPayum()
        ;
    }

    public function testShouldAllowGetGatewayAddedAsInstance(): void
    {
        $expectedGateway = new Gateway();

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGateway('a_gateway', $expectedGateway)
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedGateway, $payum->getGateway('a_gateway'));
    }

    public function testShouldAllowGetGatewayAddedAsConfig(): void
    {
        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGateway('a_gateway', [
                'factory' => 'offline',
            ])
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertInstanceOf(Gateway::class, $payum->getGateway('a_gateway'));
    }

    public function testThrowIfTryToAddGatewayConfigWithoutFactoryKeySet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gateway config must have factory set in it and it must not be empty.');
        (new PayumBuilder())
            ->addDefaultStorages()
            ->addGateway('a_gateway', [])
            ->getPayum()
        ;
    }

    public function testShouldAllowGetStorageAddedAsInstance(): void
    {
        /** @var StorageInterface<object> $expectedStorage */
        $expectedStorage = $this->createMock(StorageInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addStorage($expectedStorage::class, $expectedStorage)
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedStorage, $payum->getStorage($expectedStorage::class));
    }

    public function testShouldAllowGetGatewayFactoryAddedAsInstance(): void
    {
        /** @var GatewayFactoryInterface $expectedFactory */
        $expectedFactory = $this->createMock(GatewayFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGatewayFactory('a_factory', $expectedFactory)
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedFactory, $payum->getGatewayFactory('a_factory'));
    }

    public function testShouldAllowGetGatewayFactoryAddedAsCallbackFactory(): void
    {
        $expectedFactory = null;

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGatewayFactory('a_factory', function (array $config, GatewayFactoryInterface $coreGatewayFactory) use (&$expectedFactory) {
                $expectedFactory = new GatewayFactory($config, $coreGatewayFactory);

                return $expectedFactory;
            })
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($expectedFactory, $payum->getGatewayFactory('a_factory'));
    }

    public function testShouldPassAddedConfigToGatewayCallbackFactory(): void
    {
        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGatewayFactoryConfig('a_factory', [
                'foo' => 'fooVal',
            ])
            ->addGatewayFactory('a_factory', function (array $config, GatewayFactoryInterface $coreGatewayFactory) use (&$expectedFactory) {
                $this->assertSame([
                    'foo' => 'fooVal',
                ], $config);

                return new GatewayFactory($config, $coreGatewayFactory);
            })
            ->getPayum()
        ;
    }

    public function testShouldReuseAddedFactoriesForGatewayCreatedFromConfig(): void
    {
        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGatewayFactory('a_factory', new OfflineGatewayFactory())
            ->addGateway('a_gateway', [
                'factory' => 'a_factory',
            ])
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertInstanceOf(Gateway::class, $payum->getGateway('a_gateway'));
    }

    public function testShouldReuseGatewaysFromMainRegistryAndFallbackOne(): void
    {
        $fallbackGateway = new Gateway();
        $mainGateway = new Gateway();

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGateway('fallback_factory', $fallbackGateway)
            ->setMainRegistry(new SimpleRegistry([
                'main_gateway' => $mainGateway,
            ]))
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($mainGateway, $payum->getGateway('main_gateway'));
        $this->assertSame($fallbackGateway, $payum->getGateway('fallback_factory'));
    }

    public function testShouldAllowSetReuseGatewaysFromMainRegistryAndFallbackOne(): void
    {
        $fallbackGateway = new Gateway();
        $mainGateway = new Gateway();

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGateway('fallback_factory', $fallbackGateway)
            ->setMainRegistry(new SimpleRegistry([
                'main_gateway' => $mainGateway,
            ]))
            ->getPayum()
        ;

        $this->assertInstanceOf(Payum::class, $payum);
        $this->assertSame($mainGateway, $payum->getGateway('main_gateway'));
        $this->assertSame($fallbackGateway, $payum->getGateway('fallback_factory'));
    }

    public function testShouldUseCustomCoreGatewayFactory(): void
    {
        $expectedCoreGatewayFactory = $this->createMock(GatewayFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setCoreGatewayFactory($expectedCoreGatewayFactory)
            ->getPayum()
        ;

        $gatewayFactory = $payum->getGatewayFactory('offline');

        $this->assertInstanceOf(OfflineGatewayFactory::class, $gatewayFactory);

        $ref = new ReflectionProperty($gatewayFactory, 'coreGatewayFactory');
        $ref->setAccessible(true);

        $this->assertSame($expectedCoreGatewayFactory, $ref->getValue($gatewayFactory));
    }

    public function testShouldUseCoreGatewayFactoryBuilder(): void
    {
        $expectedCoreGatewayFactory = $this->createMock(GatewayFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setCoreGatewayFactory(function ($config) use ($expectedCoreGatewayFactory) {
                $this->assertIsArray($config);
                $this->assertNotEmpty($config);

                return $expectedCoreGatewayFactory;
            })
            ->getPayum()
        ;

        $gatewayFactory = $payum->getGatewayFactory('offline');

        $this->assertInstanceOf(OfflineGatewayFactory::class, $gatewayFactory);

        $ref = new ReflectionProperty($gatewayFactory, 'coreGatewayFactory');
        $ref->setAccessible(true);

        $this->assertSame($expectedCoreGatewayFactory, $ref->getValue($gatewayFactory));
    }

    public function testShouldAddStorageExtensionForTheAddedStorage(): void
    {
        /** @var StorageInterface<object> $expectedStorage */
        $expectedStorage = $this->createMock(StorageInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addStorage(TestModel::class, $expectedStorage)
            ->setCoreGatewayFactory(function ($config) use ($expectedStorage) {
                $this->assertIsArray($config);
                $this->assertArrayHasKey('payum.extension.storage_payum_core_tests_testmodel', $config, var_export($config, true));
                $this->assertInstanceOf(StorageExtension::class, $config['payum.extension.storage_payum_core_tests_testmodel']);

                $ref = new ReflectionProperty($config['payum.extension.storage_payum_core_tests_testmodel'], 'storage');
                $ref->setAccessible(true);

                $this->assertSame($expectedStorage, $ref->getValue($config['payum.extension.storage_payum_core_tests_testmodel']));

                return new CoreGatewayFactory($config);
            })
            ->getPayum()
        ;

        $gatewayFactory = $payum->getGatewayFactory('offline');

        $this->assertInstanceOf(OfflineGatewayFactory::class, $gatewayFactory);

        $config = $gatewayFactory->createConfig([]);

        $this->assertArrayHasKey('payum.extension.storage_payum_core_tests_testmodel', $config, var_export($config, true));
        $this->assertInstanceOf(StorageExtension::class, $config['payum.extension.storage_payum_core_tests_testmodel']);

        $ref = new ReflectionProperty($config['payum.extension.storage_payum_core_tests_testmodel'], 'storage');
        $ref->setAccessible(true);

        $this->assertSame($expectedStorage, $ref->getValue($config['payum.extension.storage_payum_core_tests_testmodel']));
    }

    public function testShouldAllowAddGatewayFactorySpecificConfig(): void
    {
        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->addGatewayFactoryConfig('offline', [
                'foo' => 'fooVal',
            ])

            ->getPayum()
        ;

        $gatewayFactory = $payum->getGatewayFactory('offline');

        $this->assertInstanceOf(OfflineGatewayFactory::class, $gatewayFactory);

        $config = $gatewayFactory->createConfig([]);

        $this->assertArrayHasKey('foo', $config, var_export($config, true));
        $this->assertSame('fooVal', $config['foo']);
    }

    public function testThrowsIfCoreGatewayFactoryBuilderReturnInvalidInstance(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Builder returned invalid instance');
        $expectedCoreGateway = $this->createMock(GatewayFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setCoreGatewayFactory(fn () => new stdClass())
            ->getPayum()
        ;

        $gatewayFactory = $payum->getGatewayFactory('offline');

        $this->assertInstanceOf(OfflineGatewayFactory::class, $gatewayFactory);

        $ref = new ReflectionProperty($gatewayFactory, 'coreGatewayFactory');
        $ref->setAccessible(true);

        $this->assertSame($expectedCoreGateway, $ref->getValue($gatewayFactory));
    }

    public function testShouldRegisterOmnipayV3Factories(): void
    {
        if (! class_exists(OmnipayGateway::class)) {
            $this->markTestSkipped('Either omnipay or\and omnipay bridge are not installed. Skip');
        }

        $expectedCoreGatewayFactory = $this->createMock(GatewayFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setCoreGatewayFactory($expectedCoreGatewayFactory)
            ->getPayum()
        ;

        $gatewayFactories = $payum->getGatewayFactories();

        $this->assertArrayHasKey('omnipay', $gatewayFactories);
    }

    public function testShouldInjectCoreGatewayFactoryToOmnipayV3Factory(): void
    {
        if (! class_exists(OmnipayGateway::class)) {
            $this->markTestSkipped('Either omnipay or\and omnipay bridge are not installed. Skip');
        }

        $expectedCoreGatewayFactory = $this->createMock(GatewayFactoryInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setCoreGatewayFactory($expectedCoreGatewayFactory)
            ->getPayum()
        ;

        $gatewayFactory = $payum->getGatewayFactory('omnipay');

        $this->assertInstanceOf(OmnipayGatewayFactory::class, $gatewayFactory);

        $ref = new ReflectionProperty($gatewayFactory, 'coreGatewayFactory');
        $ref->setAccessible(true);

        $this->assertSame($expectedCoreGatewayFactory, $ref->getValue($gatewayFactory));
    }

    public function testShouldInjectExpectedOmnipayV3GatewayInstanceAsApi(): void
    {
        if (! class_exists(OmnipayGateway::class)) {
            $this->markTestSkipped('Either omnipay or\and omnipay bridge are not installed. Skip');
        }

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->getPayum()
        ;

        $gatewayFactory = $payum->getGatewayFactory('omnipay');

        $this->assertInstanceOf(OmnipayGatewayFactory::class, $gatewayFactory);

        $gateway = $gatewayFactory->create([
            'type' => 'dummy',
        ]);

        $ref = new ReflectionProperty($gateway, 'apis');
        $ref->setAccessible(true);
        $apis = $ref->getValue($gateway);

        $this->assertCount(2, $apis);
        $this->assertInstanceOf(OmnipayGateway::class, $apis[1]);
        $this->assertSame('Dummy', $apis[1]->getName());
    }

    public function testShouldAddTokenStorageToCoreGatewayConfig(): void
    {
        $tokenStorageMock = $this->createMock(StorageInterface::class);

        $payum = (new PayumBuilder())
            ->addDefaultStorages()
            ->setTokenStorage($tokenStorageMock)
            ->setCoreGatewayFactory(function ($config) use ($tokenStorageMock) {
                $this->assertIsArray($config);
                $this->assertArrayHasKey('payum.security.token_storage', $config);
                $this->assertSame($tokenStorageMock, $config['payum.security.token_storage']);

                return new CoreGatewayFactory();
            })
            ->getPayum()
        ;
    }

    public function testShouldAllowAddCoreGatewayConfig(): void
    {
        $payumBuilder = new PayumBuilder();

        $payumBuilder->setCoreGatewayFactoryConfig([
            'foo' => 'fooVal',
            'bar' => 'barVal',
        ]);
        $ref = new ReflectionProperty($payumBuilder, 'coreGatewayFactoryConfig');
        $ref->setAccessible(true);

        $this->assertSame([
            'foo' => 'fooVal',
            'bar' => 'barVal',
        ], $ref->getValue($payumBuilder));

        $payumBuilder->addCoreGatewayFactoryConfig([
            'baz' => 'bazVal',
            'foo' => 'fooNewVal',
        ]);

        $this->assertSame([
            'foo' => 'fooNewVal',
            'bar' => 'barVal',
            'baz' => 'bazVal',
        ], $ref->getValue($payumBuilder));
    }

    public function testShouldAllowAddGatewayConfigSeveralTimes(): void
    {
        $payumBuilder = new PayumBuilder();

        $payumBuilder->addGateway('foo', [
            'factory' => 'aFactory',
            'foo' => 'fooVal',
            'bar' => 'barVal',
        ]);
        $ref = new ReflectionProperty($payumBuilder, 'gatewayConfigs');
        $ref->setAccessible(true);

        $this->assertSame([
            'foo' => [
                'factory' => 'aFactory',
                'foo' => 'fooVal',
                'bar' => 'barVal',

            ],
        ], $ref->getValue($payumBuilder));

        $payumBuilder->addGateway('foo', [
            'baz' => 'bazVal',
            'foo' => 'fooNewVal',
        ]);

        $this->assertSame([
            'foo' => [
                'factory' => 'aFactory',
                'foo' => 'fooNewVal',
                'bar' => 'barVal',
                'baz' => 'bazVal',

            ],
        ], $ref->getValue($payumBuilder));
    }

    public function testShouldAllowAddGatewayFactoryConfigSeveralTimes(): void
    {
        $payumBuilder = new PayumBuilder();

        $payumBuilder->addGatewayFactoryConfig('foo', [
            'foo' => 'fooVal',
            'bar' => 'barVal',
        ]);
        $ref = new ReflectionProperty($payumBuilder, 'gatewayFactoryConfigs');
        $ref->setAccessible(true);

        $this->assertSame([
            'foo' => [
                'foo' => 'fooVal',
                'bar' => 'barVal',

            ],
        ], $ref->getValue($payumBuilder));

        $payumBuilder->addGatewayFactoryConfig('foo', [
            'baz' => 'bazVal',
            'foo' => 'fooNewVal',
        ]);

        $this->assertSame([
            'foo' => [
                'foo' => 'fooNewVal',
                'bar' => 'barVal',
                'baz' => 'bazVal',

            ],
        ], $ref->getValue($payumBuilder));
    }

    public function testShouldAllowBuildGatewayWithCoreGatewayFactory(): void
    {
        $payumBuilder = new PayumBuilder();

        $payum = $payumBuilder
            ->addDefaultStorages()
            ->addGateway('foo', [
                'factory' => 'core',
            ])

            ->getPayum()
        ;

        $gateway = $payum->getGateway('foo');

        $this->assertInstanceOf(Gateway::class, $gateway);
    }

    /**
     * @return MockObject|RegistryInterface<object>
     */
    protected function createRegistryMock(): MockObject | RegistryInterface
    {
        return $this->createMock(RegistryInterface::class);
    }

    protected function createHttpRequestVerifierMock(): MockObject | HttpRequestVerifierInterface
    {
        return $this->createMock(HttpRequestVerifierInterface::class);
    }

    protected function createGenericTokenFactoryMock(): MockObject | GenericTokenFactoryInterface
    {
        return $this->createMock(GenericTokenFactoryInterface::class);
    }
}

class TestModel
{
}
