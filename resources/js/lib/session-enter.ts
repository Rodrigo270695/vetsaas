export const SESSION_ENTER_CLASS = 'session-enter';
export const SESSION_ENTER_KEY = 'vetsaas.session-enter';

export function markSessionEnter(): void {
    try {
        sessionStorage.setItem(SESSION_ENTER_KEY, '1');
    } catch {
        /* private mode */
    }
    document.documentElement.classList.add(SESSION_ENTER_CLASS);
}

export function endSessionEnter(): void {
    document.documentElement.classList.remove(SESSION_ENTER_CLASS);
    try {
        sessionStorage.removeItem(SESSION_ENTER_KEY);
    } catch {
        /* private mode */
    }
}

export function hasSessionEnter(): boolean {
    if (document.documentElement.classList.contains(SESSION_ENTER_CLASS)) {
        return true;
    }

    try {
        return sessionStorage.getItem(SESSION_ENTER_KEY) === '1';
    } catch {
        return false;
    }
}

export function restoreSessionEnterClass(): boolean {
    if (!hasSessionEnter()) {
        return false;
    }

    document.documentElement.classList.add(SESSION_ENTER_CLASS);

    return true;
}
