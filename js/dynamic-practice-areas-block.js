/**
 * Dynamic Practice Areas Block
 */
(function(blocks, element, blockEditor, components) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var TextControl = components.TextControl;
    var PanelBody = components.PanelBody;
    
    blocks.registerBlockType('dynamic-practice-areas/block', {
        title: 'Dynamic Practice Areas',
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            title: {
                type: 'string',
                default: 'Practice Areas'
            },
            blockId: {
                type: 'string',
                default: 'block-' + Math.random().toString(36).substring(2, 15)
            }
        },
        
        edit: function(props) {
            var title = props.attributes.title;
            
            return [
                // Block inspector controls
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, {
                        title: 'Settings',
                        initialOpen: true
                    },
                        el(TextControl, {
                            label: 'Title',
                            value: title,
                            onChange: function(newTitle) {
                                props.setAttributes({ title: newTitle });
                            }
                        })
                    )
                ),
                
                // Block edit view
                el('div', { className: props.className },
                    el('h2', { className: 'practice-areas-block-title' }, title),
                    el('div', { className: 'block-preview' },
                        el('p', { className: 'block-description' }, 'This block will display practice areas for the selected city.')
                    )
                )
            ];
        },
        
        save: function() {
            // Dynamic block, render is handled by PHP
            return null;
        }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
));