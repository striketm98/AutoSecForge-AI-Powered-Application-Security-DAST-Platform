from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route('/v1/chat/completions', methods=['POST'])
def chat():
    return jsonify({"choices": [{"message": {"content": "AutoSecForge AI local fallback"}}]})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=6400)
