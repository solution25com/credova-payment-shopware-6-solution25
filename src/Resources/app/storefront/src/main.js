import CredovaPlugin from './credova-plugin/credova.plugin';
import PayLaterPlugin from "./credova-plugin/pay-later.plugin";

const PluginManager = window.PluginManager;

PluginManager.register('CredovaPlugin', CredovaPlugin, '[data-credova-pay-later]');
PluginManager.register('PayLaterPlugin', PayLaterPlugin, '[credova-payment-pay-later]');