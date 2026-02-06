<?php

namespace Thrift\Server;

use Thrift\Exception\TTransportException;

/**
 * Simple implemtation of a Thrift server.
 *
 * @package thrift.server
 */
class TSimpleServer extends TServer
{
    /**
     * Flag for the main serving loop
     */
    private bool $stop_ = false;

    /**
     * Listens for new client using the supplied
     * transport. It handles TTransportExceptions
     * to avoid timeouts etc killing it
     *
     * @return void
     */
    public function serve(): void
    {
        $this->transport_->listen();

        while (!$this->stop_) {
            try {
                $transport = $this->transport_->accept();

                if ($transport != null) {
                    $inputTransport = $this->inputTransportFactory_->getTransport($transport);
                    $outputTransport = $this->outputTransportFactory_->getTransport($transport);
                    $inputProtocol = $this->inputProtocolFactory_->getProtocol($inputTransport);
                    $outputProtocol = $this->outputProtocolFactory_->getProtocol($outputTransport);
                    while ($this->processor_->process($inputProtocol, $outputProtocol)) {
                    }
                }
            } catch (TTransportException $e) {
            }
        }
    }

    /**
     * Stops the server running. Kills the transport
     * and then stops the main serving loop
     *
     * @return void
     */
    public function stop(): void
    {
        $this->transport_->close();
        $this->stop_ = true;
    }
}
