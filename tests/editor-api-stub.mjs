export const JoomlaEditorButton = {
  registerAction(name, handler) { window.mediaActions ??= {}; window.mediaActions[name] = handler; },
};
export const JoomlaEditor = { get(id) { return window.testEditors?.[id]; } };
