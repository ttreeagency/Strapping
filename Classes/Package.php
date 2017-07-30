<?php
namespace Ttree\Strapping;

use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Core\Booting\Sequence;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Monitor\FileMonitor;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Flow\Package\PackageManagerInterface;
use Neos\Fusion\Core\Cache\FileMonitorListener;

class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $context = $bootstrap->getContext();
        if (!$context->isProduction()) {
            $dispatcher->connect(Sequence::class, 'afterInvokeStep', function ($step) use ($bootstrap, $dispatcher) {
                if ($step->getIdentifier() === 'neos.flow:systemfilemonitor') {
                    $fileMonitor = FileMonitor::createFileMonitorAtBoot('Fusion_Files', $bootstrap);
                    $packageManager = $bootstrap->getEarlyInstance(PackageManagerInterface::class);
                    foreach ($packageManager->getActivePackages() as $packageKey => $package) {
                        if ($packageManager->isPackageFrozen($packageKey)) {
                            continue;
                        }

                        $paths = array(
                            $package->getResourcesPath() . 'Private/Components'
                        );
                        foreach ($paths as $fusionPath) {
                            if (is_dir($fusionPath)) {
                                $fileMonitor->monitorDirectory($fusionPath);
                            }
                        }
                    }

                    $fileMonitor->detectChanges();
                    $fileMonitor->shutdownObject();
                }

                if ($step->getIdentifier() === 'neos.flow:cachemanagement') {
                    $cacheManager = $bootstrap->getEarlyInstance(CacheManager::class);
                    $listener = new FileMonitorListener($cacheManager);
                    $dispatcher->connect(FileMonitor::class, 'filesHaveChanged', $listener, 'flushContentCacheOnFileChanges');
                }
            });
        }
    }
}
