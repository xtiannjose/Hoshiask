import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { announcePopupOpened, onPopupOpened } from './exclusive-popups';
import { registerAlpineStateCollections } from './theme-state';

describe('exclusive popups', function () {
    it('tells every listener which popup opened', function () {
        const bus = new EventTarget();
        const heard = [];
        onPopupOpened((source) => heard.push(['a', source]), bus);
        onPopupOpened((source) => heard.push(['b', source]), bus);

        announcePopupOpened('notifications', bus);

        expect(heard).toEqual([['a', 'notifications'], ['b', 'notifications']]);
    });

    it('stops listening once the returned function is called', function () {
        const bus = new EventTarget();
        const heard = [];
        const stop = onPopupOpened((source) => heard.push(source), bus);

        announcePopupOpened('one', bus);
        stop();
        announcePopupOpened('two', bus);

        expect(heard).toEqual(['one']);
    });
});

describe('header popups', function () {
    let components;
    const originalWindow = globalThis.window;

    // Alpine copies these objects into reactive components and runs init() with `this` set to the component.
    const start = (name) => {
        const component = components[name]();
        component.init();

        return component;
    };

    beforeEach(function () {
        globalThis.window = new EventTarget();
        components = {};
        registerAlpineStateCollections({ data: (name, factory) => { components[name] = factory; } });
    });

    afterEach(function () {
        globalThis.window = originalWindow;
    });

    it('closes the notifications when the account menu is opened', function () {
        const notifications = start('headerNotificationDropdown');
        const account = start('profileMenuDropdown');

        notifications.toggleNotifications();
        expect(notifications.notificationOpen).toBe(true);

        account.toggleMenu();

        expect(account.open).toBe(true);
        expect(notifications.notificationOpen).toBe(false);
    });

    it('closes the account menu when the notifications are opened', function () {
        const notifications = start('headerNotificationDropdown');
        const account = start('profileMenuDropdown');

        account.toggleMenu();
        notifications.toggleNotifications();

        expect(notifications.notificationOpen).toBe(true);
        expect(account.open).toBe(false);
    });

    it('closes both when another kind of popup opens, but leaves them alone when closing', function () {
        const notifications = start('headerNotificationDropdown');
        const account = start('profileMenuDropdown');

        notifications.toggleNotifications();
        announcePopupOpened('styled-dropdown');
        expect(notifications.notificationOpen).toBe(false);

        account.toggleMenu();
        announcePopupOpened('message-menu');
        expect(account.open).toBe(false);

        notifications.toggleNotifications();
        notifications.toggleNotifications();
        expect(notifications.notificationOpen).toBe(false);
    });

    it('keeps a popup open when it is the one that announced itself', function () {
        const account = start('profileMenuDropdown');

        account.toggleMenu();
        announcePopupOpened('account-menu');

        expect(account.open).toBe(true);
    });
});
