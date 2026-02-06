<?php

namespace Thrift\Server;

use Thrift\Factory\TTransportFactoryInterface;
use Thrift\Factory\TProtocolFactory;

/**
 * Generic class for a Thrift server.
 *
 * @package thrift.server
 */
abstract class TServer
{
    /**
     * Processor to handle new clients
     */
    protected object $processor_;

    /**
     * Server transport to be used for listening
     * and accepting new clients
     */
    protected TServerTransport $transport_;

    /**
     * Input transport factory
     */
    protected TTransportFactoryInterface $inputTransportFactory_;

    /**
     * Output transport factory
     */
    protected TTransportFactoryInterface $outputTransportFactory_;

    /**
     * Input protocol factory
     */
    protected TProtocolFactory $inputProtocolFactory_;

    /**
     * Output protocol factory
     */
    protected TProtocolFactory $outputProtocolFactory_;

    /**
     * Sets up all the factories, etc
     *
     * @param object $processor
     * @param TServerTransport $transport
     * @param TTransportFactoryInterface $inputTransportFactory
     * @param TTransportFactoryInterface $outputTransportFactory
     * @param TProtocolFactory $inputProtocolFactory
     * @param TProtocolFactory $outputProtocolFactory
     */
    public function __construct(
        object $processor,
        TServerTransport $transport,
        TTransportFactoryInterface $inputTransportFactory,
        TTransportFactoryInterface $outputTransportFactory,
        TProtocolFactory $inputProtocolFactory,
        TProtocolFactory $outputProtocolFactory
    ) {
        $this->processor_ = $processor;
        $this->transport_ = $transport;
        $this->inputTransportFactory_ = $inputTransportFactory;
        $this->outputTransportFactory_ = $outputTransportFactory;
        $this->inputProtocolFactory_ = $inputProtocolFactory;
        $this->outputProtocolFactory_ = $outputProtocolFactory;
    }

    /**
     * Serves the server. This should never return
     * unless a problem permits it to do so or it
     * is interrupted intentionally
     *
     * @abstract
     * @return void
     */
    abstract public function serve(): void;

    /**
     * Stops the server serving
     *
     * @abstract
     * @return void
     */
    abstract public function stop(): void;
}
