import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';

class PalmListScreen extends StatelessWidget {
  const PalmListScreen({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Palmistry')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            load: () async => unwrapList(await state.api.get('/palmistry')),
            isEmpty: (l) => l.isEmpty,
            emptyText: 'No palm readings yet. Buy a palmistry reading from Services.',
            builder: (context, list, reload) => ListView(padding: const EdgeInsets.all(16), children: [
              for (final p in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: const Icon(Icons.back_hand_outlined),
                    title: const Text('Palm reading'),
                    subtitle: Padding(padding: const EdgeInsets.only(top: 4), child: Row(children: [StatusChip(p['status'])])),
                    trailing: const Icon(Icons.chevron_right, semanticLabel: 'Open'),
                    onTap: () async {
                      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PalmDetailScreen(state: state, ref: '${p['ref']}')));
                      await reload();
                    },
                  ),
                ),
            ]),
          ),
        ),
      );
}

class PalmDetailScreen extends StatefulWidget {
  const PalmDetailScreen({super.key, required this.state, required this.ref});
  final AppState state;
  final String ref;
  @override
  State<PalmDetailScreen> createState() => _PalmDetailScreenState();
}

class _PalmDetailScreenState extends State<PalmDetailScreen> {
  int _v = 0;

  static const _statusText = {
    'awaiting_photos': 'Please upload clear photos of both palms.',
    'submitted': 'Photos received. Our team will review them shortly.',
    'in_review': 'Your palms are being read. We will notify you when the reading is ready.',
    'needs_new_photos': 'We need new photos to give you an accurate reading.',
  };

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Palm reading')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            key: ValueKey(_v),
            load: () async => unwrap(await widget.state.api.get('/palmistry/${widget.ref}')),
            builder: (context, p, _) => ListView(padding: const EdgeInsets.all(16), children: [
              Row(children: [StatusChip(p['status'])]),
              if (_statusText[p['status']] != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_statusText[p['status']]!)),
              if (p['status'] == 'needs_new_photos' && p['staff_note'] != null)
                Container(
                  margin: const EdgeInsets.only(top: 12),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: ArjColors.gold50, borderRadius: BorderRadius.circular(12), border: Border.all(color: ArjColors.gold200)),
                  child: Text('Note from our team: ${p['staff_note']}'),
                ),
              if (p['status'] == 'completed') ...[
                if (p['completed_at'] != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('Completed ${formatWhen(p['completed_at'], pattern: 'd MMM yyyy')}', style: const TextStyle(color: ArjColors.ink600))),
                SectionsView((p['sections'] as List?) ?? const []),
              ],
              if (p['can_upload'] == true) PalmUploadForm(state: widget.state, ref: widget.ref, onDone: () => setState(() => _v++)),
            ]),
          ),
        ),
      );
}

class PalmUploadForm extends StatefulWidget {
  const PalmUploadForm({super.key, required this.state, required this.ref, required this.onDone, this.picker});
  final AppState state;
  final String ref;
  final VoidCallback onDone;
  final ImagePicker? picker;
  @override
  State<PalmUploadForm> createState() => _PalmUploadFormState();
}

class _PalmUploadFormState extends State<PalmUploadForm> {
  late final _picker = widget.picker ?? ImagePicker();
  final _age = TextEditingController();
  final _question = TextEditingController();
  XFile? _left, _right;
  String _dominant = 'right';
  String? _gender, _language;
  bool _consent = false, _busy = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _age.dispose();
    _question.dispose();
    super.dispose();
  }

  Future<void> _pick(bool left) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.photo_camera_outlined), title: const Text('Take a photo'), onTap: () => Navigator.pop(c, ImageSource.camera)),
          ListTile(leading: const Icon(Icons.photo_library_outlined), title: const Text('Choose from gallery'), onTap: () => Navigator.pop(c, ImageSource.gallery)),
        ]),
      ),
    );
    if (source == null) return;
    try {
      final f = await _picker.pickImage(source: source, imageQuality: 85, maxWidth: 2000);
      if (f != null && mounted) setState(() => left ? _left = f : _right = f);
    } catch (_) {
      if (mounted) showSnack(context, 'Could not open the ${source == ImageSource.camera ? 'camera' : 'gallery'}.');
    }
  }

  Future<void> _submit() async {
    final errs = <String, String>{};
    if (_left == null) errs['left'] = 'Add a photo of your left palm.';
    if (_right == null) errs['right'] = 'Add a photo of your right palm.';
    final age = _age.text.trim();
    if (age.isNotEmpty && (int.tryParse(age) == null || int.parse(age) < 1 || int.parse(age) > 120)) errs['age'] = 'Enter a valid age.';
    if (!_consent) errs['consent'] = 'Please give consent so we can read your palms.';
    if (errs.isNotEmpty) return setState(() => _errors = errs);
    setState(() { _busy = true; _errors = {}; });
    try {
      final q = _question.text.trim();
      final form = FormData.fromMap({
        'left': await MultipartFile.fromFile(_left!.path, filename: 'left${_ext(_left!)}'),
        'right': await MultipartFile.fromFile(_right!.path, filename: 'right${_ext(_right!)}'),
        'dominant_hand': _dominant,
        if (age.isNotEmpty) 'age': age,
        if (_gender != null) 'gender': _gender,
        if (_language != null) 'language': _language,
        if (q.isNotEmpty) 'question': q,
        'consent': '1',
      });
      await widget.state.api.postMultipart('/palmistry/${widget.ref}/photos', form);
      if (!mounted) return;
      showSnack(context, 'Photos uploaded. Thank you!');
      widget.onDone();
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : e.fieldErrors);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _ext(XFile f) {
    final i = f.path.lastIndexOf('.');
    return i < 0 ? '.jpg' : f.path.substring(i);
  }

  Widget _photo(String label, XFile? f, bool left) => Expanded(
        child: Semantics(
          button: true,
          label: f == null ? 'Add $label photo' : 'Change $label photo',
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: _busy ? null : () => _pick(left),
            child: Container(
              height: 160,
              decoration: BoxDecoration(
                color: ArjColors.ivory100,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: _errors[left ? 'left' : 'right'] != null ? ArjColors.danger700 : ArjColors.ink200),
              ),
              clipBehavior: Clip.antiAlias,
              child: f == null
                  ? Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Icon(Icons.add_a_photo_outlined, color: ArjColors.ink600),
                      const SizedBox(height: 6),
                      Text(label, textAlign: TextAlign.center),
                    ])
                  : Stack(fit: StackFit.expand, children: [
                      Image.file(File(f.path), fit: BoxFit.cover, semanticLabel: label),
                      Align(alignment: Alignment.bottomCenter, child: Container(width: double.infinity, color: Colors.black54, padding: const EdgeInsets.all(4), child: Text(label, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white)))),
                    ]),
            ),
          ),
        ),
      );

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const SectionTitle('Upload your palm photos'),
        const Text('Use daylight, keep the whole palm and fingers in the frame, and avoid shadows.', style: TextStyle(color: ArjColors.ink600)),
        const SizedBox(height: 12),
        Row(children: [_photo('Left palm', _left, true), const SizedBox(width: 12), _photo('Right palm', _right, false)]),
        FormError(_errors['left'] ?? _errors['right']),
        const SizedBox(height: 16),
        const Text('Dominant hand'),
        const SizedBox(height: 6),
        SegmentedButton<String>(
          segments: const [ButtonSegment(value: 'left', label: Text('Left')), ButtonSegment(value: 'right', label: Text('Right'))],
          selected: {_dominant},
          onSelectionChanged: (s) => setState(() => _dominant = s.first),
        ),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: TextField(controller: _age, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Age (optional)', errorText: _errors['age']))),
          const SizedBox(width: 12),
          Expanded(
            child: DropdownButtonFormField<String?>(
              initialValue: _gender,
              decoration: InputDecoration(labelText: 'Gender (optional)', errorText: _errors['gender']),
              items: const [
                DropdownMenuItem(value: null, child: Text('—')),
                DropdownMenuItem(value: 'male', child: Text('Male')),
                DropdownMenuItem(value: 'female', child: Text('Female')),
                DropdownMenuItem(value: 'other', child: Text('Other')),
              ],
              onChanged: (v) => setState(() => _gender = v),
            ),
          ),
        ]),
        const SizedBox(height: 12),
        DropdownButtonFormField<String?>(
          initialValue: _language,
          decoration: InputDecoration(labelText: 'Reading language (optional)', errorText: _errors['language']),
          items: const [DropdownMenuItem(value: null, child: Text('—')), DropdownMenuItem(value: 'en', child: Text('English')), DropdownMenuItem(value: 'hi', child: Text('हिन्दी'))],
          onChanged: (v) => setState(() => _language = v),
        ),
        const SizedBox(height: 12),
        TextField(controller: _question, maxLines: 3, maxLength: 500, decoration: InputDecoration(labelText: 'Your question (optional)', errorText: _errors['question'])),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _consent,
          onChanged: (v) => setState(() => _consent = v ?? false),
          title: const Text('I consent to Astro Rajnath Jha storing and viewing these photos to prepare my palm reading.'),
          subtitle: _errors['consent'] != null ? Text(_errors['consent']!, style: TextStyle(color: Theme.of(context).colorScheme.error)) : null,
        ),
        FormError(_errors['_'] ?? _errors['dominant_hand']),
        const SizedBox(height: 12),
        FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Uploading…' : 'Upload photos')),
      ]);
}
