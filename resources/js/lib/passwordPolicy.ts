/**
 * Reglas espejo de `Password::defaults()` en AppServiceProvider (producción).
 * El chequeo `uncompromised` solo ocurre en servidor.
 */
export const PASSWORD_MIN_LENGTH = 12;

export type PasswordRequirementId =
    | 'length'
    | 'mixed'
    | 'number'
    | 'symbol';

export type PasswordRequirement = {
    id: PasswordRequirementId;
    met: boolean;
};

export type PasswordStrengthLevel =
    | 'empty'
    | 'weak'
    | 'fair'
    | 'good'
    | 'strong';

export type PasswordAnalysis = {
    requirements: PasswordRequirement[];
    metCount: number;
    totalCount: number;
    level: PasswordStrengthLevel;
    allMet: boolean;
};

const REQUIREMENT_TESTS: Record<
    PasswordRequirementId,
    (password: string) => boolean
> = {
    length: (password) => password.length >= PASSWORD_MIN_LENGTH,
    mixed: (password) => /[a-z]/.test(password) && /[A-Z]/.test(password),
    number: (password) => /\d/.test(password),
    symbol: (password) => /[^A-Za-z0-9]/.test(password),
};

const REQUIREMENT_IDS = Object.keys(
    REQUIREMENT_TESTS,
) as PasswordRequirementId[];

export function evaluatePassword(password: string): PasswordAnalysis {
    const requirements = REQUIREMENT_IDS.map((id) => ({
        id,
        met: REQUIREMENT_TESTS[id](password),
    }));

    const metCount = requirements.filter((requirement) => requirement.met)
        .length;
    const totalCount = requirements.length;

    let level: PasswordStrengthLevel = 'empty';

    if (password.length > 0) {
        if (metCount <= 1) {
            level = 'weak';
        } else if (metCount === 2) {
            level = 'fair';
        } else if (metCount === 3) {
            level = 'good';
        } else {
            level = 'strong';
        }
    }

    return {
        requirements,
        metCount,
        totalCount,
        level,
        allMet: metCount === totalCount,
    };
}

export function passwordsMatch(
    password: string,
    confirmation: string,
): boolean {
    return confirmation.length > 0 && password === confirmation;
}

/** En build de producción exige la política completa; en local solo coincidencia. */
export function canSubmitNewPassword(
    password: string,
    passwordConfirmation: string,
    enforcePolicy = import.meta.env.PROD,
): boolean {
    if (!passwordsMatch(password, passwordConfirmation)) {
        return false;
    }

    if (!enforcePolicy) {
        return password.length > 0;
    }

    return evaluatePassword(password).allMet;
}
