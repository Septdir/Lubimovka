/*
 * @package     Lubimovka Site Package
 * @subpackage  tpl_yootheme_lubimovka
 * @version     __DEPLOY_VERSION__
 * @author      RadicalMart Team - radicalmart.ru
 * @copyright   Copyright (c) 2026 RadicalMart. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://radicalmart.ru/
 */

(function (global, factory) {
	typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
		typeof define === 'function' && define.amd ? define('customicons', factory) :
			(global = global || self, global.customiconsIcons = factory());
}(this, function () {
	'use strict';

	function plugin(UIkit) {
		if (plugin.installed) {
			return;
		}

		UIkit.icon.add({
			'lubimovka_cart': '<svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18.5002 0C15.0884 0 12.3127 2.6916 12.3127 6V8H5.09388C4.55516 8 4.10677 8.4024 4.06552 8.9232L2.003 34.9232C1.98114 35.2008 2.07932 35.4748 2.27443 35.6796C2.46996 35.8836 2.74427 36 3.03137 36H33.969C34.2561 36 34.5305 35.8836 34.7256 35.6796C34.9207 35.4752 35.0189 35.2012 34.997 34.9232L32.9345 8.9232C32.8936 8.4024 32.4453 8 31.9065 8H24.6877V6C24.6877 2.6916 21.912 0 18.5002 0ZM14.3752 6C14.3752 3.7944 16.2257 2 18.5002 2C20.7747 2 22.6252 3.7944 22.6252 6V8H14.3752V6ZM30.9516 10L32.8553 34H4.14513L6.04883 10H12.3127V13C12.3127 13.5524 12.7743 14 13.3439 14C13.9136 14 14.3752 13.5524 14.3752 13V10H22.6252V13C22.6252 13.5524 23.0868 14 23.6565 14C24.2261 14 24.6877 13.5524 24.6877 13V10H30.9516Z" fill="white"/></svg>',
			'lubimovka_office': '<svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 28C10.1429 18.6667 25.4082 18.6667 29 28M34 18C34 26.8366 26.8366 34 18 34C9.16344 34 2 26.8366 2 18C2 9.16344 9.16344 2 18 2C26.8366 2 34 9.16344 34 18ZM25 14C25 17.866 21.866 21 18 21C14.134 21 11 17.866 11 14C11 10.134 14.134 7 18 7C21.866 7 25 10.134 25 14Z" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>',
			'lubimovka_phone': '<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20.1663 15.51V18.26C20.1674 18.5153 20.1151 18.768 20.0128 19.0019C19.9105 19.2358 19.7605 19.4458 19.5724 19.6184C19.3843 19.791 19.1622 19.9224 18.9203 20.0042C18.6785 20.0859 18.4222 20.1163 18.168 20.0933C15.3473 19.7868 12.6377 18.823 10.2572 17.2792C8.04233 15.8718 6.16455 13.994 4.75715 11.7792C3.20797 9.38778 2.24388 6.66509 1.94299 3.83167C1.92008 3.57819 1.95021 3.3227 2.03145 3.0815C2.11269 2.84029 2.24326 2.61864 2.41486 2.43066C2.58645 2.24268 2.79531 2.09249 3.02813 1.98965C3.26095 1.88681 3.51263 1.83358 3.76715 1.83334H6.51715C6.96202 1.82896 7.3933 1.9865 7.7306 2.27658C8.06791 2.56666 8.28822 2.9695 8.35049 3.41001C8.46656 4.29007 8.68182 5.15417 8.99215 5.98584C9.11549 6.31394 9.14218 6.67051 9.06907 7.01331C8.99596 7.35612 8.82611 7.67078 8.57965 7.92001L7.41549 9.08417C8.72041 11.3791 10.6206 13.2792 12.9155 14.5842L14.0797 13.42C14.3289 13.1735 14.6435 13.0037 14.9863 12.9306C15.3291 12.8575 15.6857 12.8842 16.0138 13.0075C16.8455 13.3178 17.7096 13.5331 18.5897 13.6492C19.0349 13.712 19.4416 13.9363 19.7323 14.2794C20.023 14.6225 20.1775 15.0605 20.1663 15.51Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		});
	}

	if (typeof window !== 'undefined' && window.UIkit) {
		window.UIkit.use(plugin);
	}
	return plugin;
}));