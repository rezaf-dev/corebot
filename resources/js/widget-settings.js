export const defaultWidgetMessages = {
    welcome_message: 'Hi, how can I help with your CRM today?',
    fallback_message: 'I am not sure based on the available information. Please contact support, and someone can help you with this.',
};

export const defaultWidgetPrompts = ['How do I get started?', 'What can you help with?', 'Contact support'];

export function resolveWidgetText(config, bot, defaults, translations, locale) {
    const text = {};
    for (const field of ['title', 'subtitle', 'launcher_label', 'input_placeholder', 'send_button_label', 'welcome_message', 'fallback_message']) {
        const defaultText = defaultWidgetMessages[field] ?? defaults[field];
        const baseText = field in defaultWidgetMessages ? bot?.[field] || defaultText : config[field] ?? defaultText;
        const override = config.localized_content?.[locale]?.[field];
        text[field] = typeof override === 'string' && override.trim()
            ? override
            : baseText === defaultText ? translations[defaultText] || defaultText : baseText;
    }

    const overrides = config.localized_content?.[locale]?.suggested_prompts?.filter((prompt) => prompt.trim());
    const basePrompts = config.suggested_prompts?.filter((prompt) => prompt.trim());
    text.suggested_prompts = overrides?.length ? overrides : (basePrompts?.length ? basePrompts : defaultWidgetPrompts)
        .map((prompt) => defaultWidgetPrompts.includes(prompt) ? translations[prompt] || prompt : prompt);

    return text;
}
