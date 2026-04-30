import OpenAI from "openai";

const client = new OpenAI({
    apiKey: process.env.OPENAI_API_KEY,
});

const res = await client.chat.completions.create({
    model: "gpt-4.1",
    messages: [
        { role: "user", content: "buatkan fungsi javascript hello world" }
    ],
});

console.log(res.choices[0].message.content);